<?php

namespace Tests\Feature;

use App\Models\LeaveType;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class LeaveManagementFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function activeUser(array $permissions, string $roleName): User
    {
        $role = Role::findOrCreate($roleName);
        foreach ($permissions as $permission) {
            $role->givePermissionTo(Permission::findOrCreate($permission));
        }

        $user = User::factory()->create();
        $user->assignRole($role);
        StaffProfile::create([
            'user_id' => $user->id,
            'employment_status' => 'active',
        ]);

        return $user;
    }

    public function test_staff_can_submit_leave_and_it_awaits_approval(): void
    {
        $type = LeaveType::create(['name' => 'Annual Leave']);
        $staff = $this->activeUser(['leave.index', 'leave.create', 'leave.store', 'leave.show'], 'Advocate');

        $response = $this->actingAs($staff)->post(route('leave.store'), [
            'leave_type_id' => $type->id,
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-05',
            'reason' => 'Family time',
        ]);

        $this->assertDatabaseHas('leave_requests', [
            'user_id' => $staff->id,
            'status' => 'submitted',
            'days' => 5,
        ]);
        $this->assertDatabaseHas('approvals', [
            'approvable_type' => \App\Models\LeaveRequest::class,
            'status' => 'pending',
        ]);

        $leave = \App\Models\LeaveRequest::first();
        $response->assertRedirect(route('leave.show', $leave));
    }

    public function test_staff_only_sees_their_own_requests(): void
    {
        $type = LeaveType::create(['name' => 'Annual Leave']);
        $staff = $this->activeUser(['leave.index', 'leave.store', 'leave.show'], 'Advocate');
        $other = $this->activeUser(['leave.index', 'leave.store', 'leave.show'], 'Paralegal');

        \App\Models\LeaveRequest::create([
            'leave_no' => 'LV2607-0001', 'user_id' => $other->id, 'leave_type_id' => $type->id,
            'start_date' => '2026-07-01', 'end_date' => '2026-07-02', 'days' => 2, 'status' => 'submitted',
        ]);

        $this->actingAs($staff)->get(route('leave.index'))->assertOk()->assertDontSee('LV2607-0001');
    }

    public function test_approver_can_approve_request(): void
    {
        $type = LeaveType::create(['name' => 'Annual Leave']);
        $staff = $this->activeUser(['leave.store'], 'Advocate');
        $approver = $this->activeUser(['leave.index', 'leave.show', 'leave.approve', 'leave.reject'], 'HR Manager');

        $this->actingAs($staff)->post(route('leave.store'), [
            'leave_type_id' => $type->id,
            'start_date' => '2026-07-10',
            'end_date' => '2026-07-12',
        ])->assertRedirect();

        $leave = \App\Models\LeaveRequest::first();

        $this->actingAs($approver)
            ->patch(route('leave.approve', $leave), ['review_notes' => 'Approved'])
            ->assertRedirect();

        $this->assertSame('approved', $leave->fresh()->status);
        $this->assertSame('approved', $leave->fresh()->approvalStatus());
    }

    public function test_non_approver_cannot_access_approve_route(): void
    {
        $type = LeaveType::create(['name' => 'Annual Leave']);
        $staff = $this->activeUser(['leave.store', 'leave.show'], 'Advocate');

        $this->actingAs($staff)->post(route('leave.store'), [
            'leave_type_id' => $type->id,
            'start_date' => '2026-07-10',
            'end_date' => '2026-07-12',
        ]);

        $leave = \App\Models\LeaveRequest::first();

        $this->actingAs($staff)
            ->patch(route('leave.approve', $leave), [])
            ->assertForbidden();
    }
}
