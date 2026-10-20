<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Department;
use App\Models\JobTitle;
use App\Models\StaffProfile;
use App\Models\User;
use App\Notifications\StaffAccountApproved;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class BulkStaffApprovalTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private Department $department;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Notification::fake();
        JobTitle::create(['name' => 'Legal Assistant']);
        Role::findOrCreate('Advocate');
        Role::findOrCreate('Accountant');
        $this->branch = Branch::create(['name' => 'Kampala']);
        $this->department = Department::create(['name' => 'Litigation', 'branch_id' => $this->branch->id]);
    }

    private function actor(bool $canApprove = true): User
    {
        $user = User::factory()->create();
        StaffProfile::create(['user_id' => $user->id, 'employment_status' => 'active']);
        foreach (['access.users.index', 'access.approvals.index', 'access.approvals.show'] as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission));
        }
        if ($canApprove) {
            $user->givePermissionTo(Permission::findOrCreate('access.users.approve'));
        }

        return $user;
    }

    private function pending(array $overrides = []): User
    {
        $user = User::factory()->create(['email_verified_at' => null]);
        StaffProfile::create(array_merge([
            'user_id' => $user->id, 'employment_status' => 'pending', 'phone' => '+256 700 111222',
            'job_title' => 'Legal Assistant', 'branch_id' => $this->branch->id,
            'department_id' => $this->department->id, 'requested_role' => 'Advocate',
        ], $overrides));

        return $user;
    }

    public function test_five_selected_users_are_approved_with_their_own_roles_and_assignments_and_retries_do_not_repeat(): void
    {
        $actor = $this->actor();
        $users = collect(range(1, 5))->map(fn ($index) => $this->pending(['requested_role' => $index % 2 ? 'Advocate' : 'Accountant']));
        $other = $this->pending();
        $branch = Branch::create(['name' => 'Mbale']);
        $department = Department::create(['name' => 'Finance', 'branch_id' => $branch->id]);
        $users->last()->staffProfile->update(['branch_id' => $branch->id, 'department_id' => $department->id]);
        $payload = ['user_ids' => $users->pluck('id')->all(), 'search' => 'staff'];
        $this->actingAs($actor)->get(route('access.approvals.index'))->assertOk()
            ->assertSee('Select 5')->assertSee('Select 10')->assertSee('data-bulk-user', false);
        $this->patch(route('access.users.bulk-approve'), $payload)
            ->assertRedirect(route('access.approvals.index', ['search' => 'staff']))->assertSessionHas('status', '5 users approved.');
        foreach ($users as $user) {
            $user->refresh();
            $this->assertSame('active', $user->staffProfile->employment_status);
            $this->assertTrue($user->hasRole($user->staffProfile->requested_role));
            $this->assertSame($user->staffProfile->branch_id, $user->branch_id);
            $this->assertSame($user->staffProfile->department_id, $user->department_id);
            $this->assertTrue($user->hasVerifiedEmail());
            Notification::assertSentToTimes($user, StaffAccountApproved::class, 1);
        }
        $this->assertSame('pending', $other->fresh()->staffProfile->employment_status);
        $this->patch(route('access.users.bulk-approve'), $payload)->assertSessionHasNoErrors();
        $this->assertSame(5, AuditLog::where('event', 'access_approved')->count());
        foreach ($users as $user) {
            Notification::assertSentToTimes($user, StaffAccountApproved::class, 1);
        }
    }

    public function test_an_incomplete_request_rolls_back_the_entire_batch_and_sends_no_notices(): void
    {
        $actor = $this->actor();
        $valid = $this->pending();
        $invalid = $this->pending(['phone' => null]);
        $this->actingAs($actor)->patch(route('access.users.bulk-approve'), ['user_ids' => [$valid->id, $invalid->id]])
            ->assertSessionHasErrors('user_ids');
        foreach ([$valid, $invalid] as $user) {
            $this->assertSame('pending', $user->fresh()->staffProfile->employment_status);
            $this->assertFalse($user->fresh()->hasRole('Advocate'));
            $this->assertFalse($user->fresh()->hasVerifiedEmail());
        }
        $this->assertSame(0, AuditLog::where('event', 'access_approved')->count());
        Notification::assertNothingSent();
    }

    public function test_bulk_approval_requires_the_existing_approval_permission(): void
    {
        $user = $this->pending();
        $this->actingAs($this->actor(false))->get(route('access.approvals.index'))
            ->assertOk()->assertDontSee('Approve Selected');
        $this->patch(route('access.users.bulk-approve'), ['user_ids' => [$user->id]])->assertForbidden();
        $this->assertSame('pending', $user->fresh()->staffProfile->employment_status);
    }

    public function test_protected_roles_client_accounts_and_suspended_users_cannot_be_bulk_approved(): void
    {
        Role::findOrCreate('Administrator');
        $this->actingAs($this->actor());
        $protected = $this->pending(['requested_role' => 'Administrator']);
        $client = $this->pending();
        $client->update(['account_type' => 'client']);
        $suspended = $this->pending(['employment_status' => 'suspended']);
        foreach ([$protected, $client, $suspended] as $user) {
            $this->patch(route('access.users.bulk-approve'), ['user_ids' => [$user->id]])->assertSessionHasErrors('user_ids');
            $this->assertFalse($user->fresh()->hasRole('Administrator'));
            $this->assertFalse($user->fresh()->hasRole('Advocate'));
        }
        Notification::assertNothingSent();
    }

    public function test_duplicates_and_empty_selections_are_rejected(): void
    {
        $user = $this->pending();
        $this->actingAs($this->actor())->patchJson(route('access.users.bulk-approve'), ['user_ids' => []])->assertUnprocessable();
        $this->patchJson(route('access.users.bulk-approve'), ['user_ids' => [$user->id, $user->id]])->assertUnprocessable();
        $this->assertSame('pending', $user->fresh()->staffProfile->employment_status);
    }

    public function test_branch_department_mismatches_are_rejected_before_activation(): void
    {
        $user = $this->pending();
        $otherBranch = Branch::create(['name' => 'Mbarara']);
        $user->staffProfile->update(['branch_id' => $otherBranch->id]);
        $this->actingAs($this->actor())->patch(route('access.users.bulk-approve'), ['user_ids' => [$user->id]])
            ->assertSessionHasErrors('user_ids');
        $this->assertSame('pending', $user->fresh()->staffProfile->employment_status);
        Notification::assertNothingSent();
    }

    public function test_notification_delivery_failure_does_not_undo_an_approved_account(): void
    {
        $user = $this->pending();
        $dispatcher = \Mockery::mock(Dispatcher::class);
        $dispatcher->shouldReceive('send')->once()->andThrow(new \RuntimeException('Test mail transport unavailable'));
        app()->instance(Dispatcher::class, $dispatcher);
        $this->actingAs($this->actor())->patch(route('access.users.bulk-approve'), ['user_ids' => [$user->id]])
            ->assertSessionHasNoErrors()->assertSessionHas('approval_notice_failures', 1);
        $this->assertSame('active', $user->fresh()->staffProfile->employment_status);
        $this->assertTrue($user->fresh()->hasRole('Advocate'));
    }
}
