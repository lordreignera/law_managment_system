<?php

namespace Tests\Feature;

use App\Models\AttendanceCorrectionRequest;
use App\Models\AttendanceRecord;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AttendanceFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function staff(array $permissions, string $role, ?User $manager = null): User
    {
        $roleModel = Role::findOrCreate($role);
        foreach ($permissions as $permission) {
            $roleModel->givePermissionTo(Permission::findOrCreate($permission));
        }

        $user = User::factory()->create(['manager_id' => $manager?->id]);
        StaffProfile::create(['user_id' => $user->id, 'employment_status' => 'active']);
        $user->assignRole($roleModel);

        return $user;
    }

    public function test_staff_can_check_in_and_out_with_device_and_ip_details(): void
    {
        $staff = $this->staff(['attendance.index', 'attendance.check-in', 'attendance.check-out'], 'Advocate');

        $this->actingAs($staff)->withHeaders([
            'User-Agent' => 'KFMS Mobile',
            'X-Device-Name' => 'Android-Office',
        ])->post(route('attendance.check-in'))->assertRedirect();

        $this->assertDatabaseHas('attendance_records', ['user_id' => $staff->id, 'check_in_ip' => '127.0.0.1', 'check_in_device' => 'Android-Office']);
        $this->assertSame(today()->toDateString(), AttendanceRecord::where('user_id', $staff->id)->firstOrFail()->attendance_date->toDateString());

        $this->actingAs($staff)->withHeaders(['User-Agent' => 'KFMS Mobile'])->post(route('attendance.check-out'))->assertRedirect();
        $record = AttendanceRecord::where('user_id', $staff->id)->firstOrFail();
        $this->assertNotNull($record->checked_out_at);
    }

    public function test_correction_requires_manager_approval_and_updates_the_original_record(): void
    {
        $manager = $this->staff(['attendance.index', 'attendance.reports', 'attendance.corrections.approve'], 'Department Manager');
        $staff = $this->staff(['attendance.index', 'attendance.corrections.store'], 'Advocate', $manager);

        $this->actingAs($staff)->post(route('attendance.corrections.store'), [
            'attendance_date' => today()->subDay()->toDateString(),
            'requested_check_in_at' => today()->subDay()->setTime(8, 30)->format('Y-m-d H:i'),
            'requested_check_out_at' => today()->subDay()->setTime(17, 0)->format('Y-m-d H:i'),
            'reason' => 'The office network was unavailable at check-in.',
        ])->assertRedirect();

        $correction = AttendanceCorrectionRequest::firstOrFail();
        $this->assertSame('submitted', $correction->status);
        $this->assertDatabaseHas('approvals', ['approvable_type' => $correction->getMorphClass(), 'approvable_id' => $correction->id, 'status' => 'pending']);

        $this->actingAs($manager)->patch(route('attendance.corrections.approve', $correction), ['review_notes' => 'Verified with the office register.'])->assertRedirect();
        $this->assertSame('approved', $correction->fresh()->status);
        $record = AttendanceRecord::where('user_id', $staff->id)->whereDate('attendance_date', today()->subDay())->firstOrFail();
        $this->assertSame('correction', $record->source);
        $this->assertNotNull($record->checked_in_at);
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => $record->getMorphClass(), 'auditable_id' => $record->id, 'event' => 'updated']);
    }

    public function test_manager_can_view_monthly_attendance_totals(): void
    {
        $manager = $this->staff(['attendance.index', 'attendance.reports', 'attendance.monthly'], 'HR Manager');
        $staff = $this->staff(['attendance.index'], 'Advocate', $manager);
        $date = now()->startOfMonth()->addWeekday();

        AttendanceRecord::create([
            'user_id' => $staff->id,
            'attendance_date' => $date->toDateString(),
            'checked_in_at' => $date->copy()->setTime(8, 30),
            'checked_out_at' => $date->copy()->setTime(17, 0),
            'status' => 'present',
            'source' => 'self',
        ]);

        $this->actingAs($manager)->get(route('attendance.monthly', ['month' => now()->format('Y-m')]))
            ->assertOk()
            ->assertSee($staff->name)
            ->assertSee('Present entries');
    }
}
