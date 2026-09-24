<?php

namespace Tests\Feature;

use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\StaffProfile;
use App\Models\User;
use App\Notifications\AnnualLeaveReminder;
use App\Notifications\LeaveApprovalRequested;
use App\Notifications\LeaveRequestStatusChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
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
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }

    private function approvalActors(): array
    {
        $permissions = ['leave.index', 'leave.show', 'leave.approve', 'leave.reject'];

        return [
            'manager' => $this->activeUser($permissions, 'Department Manager'),
            'hr' => $this->activeUser($permissions, 'HR Manager'),
            'executive' => $this->activeUser($permissions, 'Super Admin'),
        ];
    }

    private function attachApprovalChain(User $staff): array
    {
        $actors = $this->approvalActors();
        $staff->update(['manager_id' => $actors['manager']->id]);

        return $actors;
    }

    public function test_staff_can_submit_leave_and_it_awaits_department_manager(): void
    {
        Notification::fake();
        $type = LeaveType::create(['name' => 'Annual Leave']);
        $staff = $this->activeUser(['leave.index', 'leave.create', 'leave.store', 'leave.show'], 'Advocate');
        $actors = $this->attachApprovalChain($staff);

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
        $this->assertDatabaseCount('approvals', 3);
        $this->assertDatabaseHas('approvals', [
            'approver_id' => $actors['manager']->id,
            'level' => 1,
            'status' => 'pending',
        ]);

        $leave = LeaveRequest::first();
        $response->assertRedirect(route('leave.show', $leave));
        Notification::assertSentTo($actors['manager'], LeaveApprovalRequested::class);
    }

    public function test_staff_only_sees_their_own_requests(): void
    {
        $type = LeaveType::create(['name' => 'Annual Leave']);
        $staff = $this->activeUser(['leave.index', 'leave.store', 'leave.show'], 'Advocate');
        $other = $this->activeUser(['leave.index', 'leave.store', 'leave.show'], 'Paralegal');

        LeaveRequest::create([
            'leave_no' => 'LV2607-0001', 'user_id' => $other->id, 'leave_type_id' => $type->id,
            'start_date' => '2026-07-01', 'end_date' => '2026-07-02', 'days' => 2, 'status' => 'submitted',
        ]);

        $this->actingAs($staff)->get(route('leave.index'))->assertOk()->assertDontSee('LV2607-0001');
    }

    public function test_approval_must_follow_all_three_stages(): void
    {
        Notification::fake();
        $type = LeaveType::create(['name' => 'Annual Leave']);
        $staff = $this->activeUser(['leave.store'], 'Advocate');
        $actors = $this->attachApprovalChain($staff);

        $submitResponse = $this->actingAs($staff)->post(route('leave.store'), [
            'leave_type_id' => $type->id,
            'start_date' => '2026-07-10',
            'end_date' => '2026-07-12',
        ]);
        $submitResponse->assertRedirect();

        $leave = LeaveRequest::first();

        $this->actingAs($actors['manager'])
            ->patch(route('leave.approve', $leave), ['review_notes' => 'Manager approved'])
            ->assertRedirect();
        $this->assertSame('submitted', $leave->fresh()->status);
        $this->assertSame(2, $leave->fresh()->currentApproval()->level);

        $this->actingAs($actors['hr'])
            ->patch(route('leave.approve', $leave), ['review_notes' => 'HR approved'])
            ->assertRedirect();
        $this->assertSame('submitted', $leave->fresh()->status);
        $this->assertSame(3, $leave->fresh()->currentApproval()->level);

        $this->actingAs($actors['executive'])
            ->patch(route('leave.approve', $leave), ['review_notes' => 'Final approval'])
            ->assertRedirect();

        $this->assertSame('approved', $leave->fresh()->status);
        $this->assertSame('approved', $leave->fresh()->approvalStatus());
        Notification::assertSentTo($staff, LeaveRequestStatusChanged::class, 3);
    }

    public function test_rejected_leave_can_be_rescheduled_into_a_new_round(): void
    {
        Notification::fake();
        $type = LeaveType::create(['name' => 'Annual Leave']);
        $staff = $this->activeUser(['leave.store', 'leave.show'], 'Advocate');
        $actors = $this->attachApprovalChain($staff);

        $this->actingAs($staff)->post(route('leave.store'), [
            'leave_type_id' => $type->id,
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-05',
        ]);

        $leave = LeaveRequest::first();

        $this->actingAs($actors['manager'])
            ->patch(route('leave.reject', $leave), ['review_notes' => 'Please choose different dates.'])
            ->assertRedirect();

        $this->assertSame('rejected', $leave->fresh()->status);

        $this->actingAs($staff)
            ->patch(route('leave.reschedule.update', $leave), [
                'leave_type_id' => $type->id,
                'start_date' => '2026-08-10',
                'end_date' => '2026-08-14',
            ])
            ->assertRedirect(route('leave.show', $leave));

        $this->assertSame('submitted', $leave->fresh()->status);
        $this->assertSame(1, $leave->fresh()->resubmission_count);
        $this->assertSame('pending', $leave->fresh()->approvalStatus());
        $this->assertDatabaseHas('approvals', ['round' => 1, 'status' => 'rejected']);
        $this->assertDatabaseHas('approvals', ['round' => 2, 'status' => 'pending']);
    }

    public function test_annual_leave_reminder_is_sent_once_per_year(): void
    {
        Notification::fake();
        $type = LeaveType::create(['name' => 'Annual Leave']);
        $staff = $this->activeUser([], 'Advocate');

        $this->artisan('kfms:leave-reminders', ['--year' => 2026])->assertSuccessful();
        Notification::assertSentTo($staff, AnnualLeaveReminder::class);
        $this->assertDatabaseHas('leave_year_reminders', ['user_id' => $staff->id, 'year' => 2026]);

        Notification::fake();
        $this->artisan('kfms:leave-reminders', ['--year' => 2026])->assertSuccessful();
        Notification::assertNothingSent();

        LeaveRequest::create([
            'leave_no' => 'LV2609-0001', 'user_id' => $staff->id, 'leave_type_id' => $type->id,
            'start_date' => '2026-01-01', 'end_date' => '2026-01-21', 'days' => 21, 'status' => 'approved',
        ]);
        $this->assertSame(21.0, LeaveRequest::annualScheduledDaysFor($staff, 2026));
    }

    public function test_non_approver_cannot_access_approve_route(): void
    {
        $type = LeaveType::create(['name' => 'Annual Leave']);
        $staff = $this->activeUser(['leave.store', 'leave.show'], 'Advocate');
        $actors = $this->attachApprovalChain($staff);

        $this->actingAs($staff)->post(route('leave.store'), [
            'leave_type_id' => $type->id,
            'start_date' => '2026-07-10',
            'end_date' => '2026-07-12',
        ]);

        $leave = LeaveRequest::first();

        $this->actingAs($staff)
            ->patch(route('leave.approve', $leave), [])
            ->assertForbidden();
    }
}
