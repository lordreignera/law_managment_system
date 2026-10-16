<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\LandTitle;
use App\Models\LandTitleChecklistItem;
use App\Models\LandTitleMilestone;
use App\Models\LandTitleMilestoneReminderLog;
use App\Models\LandTitleMovement;
use App\Models\StaffProfile;
use App\Models\User;
use App\Notifications\SecurityMilestoneReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SecurityWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_security_workflow_records_branch_custody_checklist_and_status_audit(): void
    {
        $branch = Branch::create(['name' => 'Kampala', 'code' => 'KLA']);
        $user = $this->activeUser($branch, [
            'land-titles.create', 'land-titles.store', 'land-titles.show', 'land-titles.update',
            'land-titles.checklist.store', 'land-titles.checklist.update', 'land-titles.checklist.destroy',
            'land-titles.movements.store',
        ]);

        $title = LandTitle::create([
            'branch_id' => $branch->id,
            'reference_no' => 'SEC26100001',
            'security_type' => 'mortgage',
            'movement_direction' => 'incoming',
            'borrower_name' => 'Borrower One',
            'title_number' => 'KLA/123/45',
            'land_reference' => 'Plot 10 Block 3',
            'instruction_details' => 'Register mortgage and retain title in custody.',
            'status' => 'pending',
        ]);
        LandTitleChecklistItem::create([
            'land_title_id' => $title->id,
            'label' => 'Original title / security received',
            'is_required' => true,
            'status' => 'pending',
            'sort_order' => 1,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => LandTitle::class,
            'auditable_id' => $title->id,
            'event' => 'created',
        ]);

        $this->actingAs($user)->put(route('land-titles.update', $title), [
            'branch_id' => $branch->id,
            'security_type' => 'mortgage',
            'movement_direction' => 'outgoing',
            'borrower_name' => 'Borrower One',
            'title_number' => 'KLA/123/45',
            'status' => 'dispatched',
            'dispatched_at' => now()->format('Y-m-d\\TH:i'),
            'dispatched_to' => 'Kampala Zonal Office',
        ])->assertRedirect(route('land-titles.show', $title));

        $this->assertDatabaseHas('land_title_movements', [
            'land_title_id' => $title->id,
            'movement_type' => 'dispatched',
            'to_party' => 'Kampala Zonal Office',
        ]);

        $item = $title->checklistItems()->firstOrFail();
        $this->actingAs($user)->patch(route('land-titles.checklist.update', [$title, $item]), [
            'status' => 'complete',
        ])->assertRedirect();

        $this->assertSame('complete', $item->fresh()->status);
        $this->assertNotNull($item->fresh()->completed_at);
        $this->assertSame(1, LandTitleMovement::where('land_title_id', $title->id)->count());
    }

    public function test_security_records_can_be_closed_only_after_required_checklist_is_complete(): void
    {
        $branch = Branch::create(['name' => 'Mbarara', 'code' => 'MBA']);
        $user = $this->activeUser($branch, ['land-titles.update', 'land-titles.show', 'land-titles.checklist.update']);
        $title = LandTitle::create([
            'branch_id' => $branch->id,
            'reference_no' => 'SEC26100002',
            'security_type' => 'land_title',
            'movement_direction' => 'incoming',
            'borrower_name' => 'Borrower Two',
            'status' => 'returned',
        ]);
        $item = LandTitleChecklistItem::create([
            'land_title_id' => $title->id,
            'label' => 'Original title received',
            'is_required' => true,
            'status' => 'pending',
        ]);

        $this->actingAs($user)->put(route('land-titles.update', $title), [
            'branch_id' => $branch->id,
            'security_type' => 'land_title',
            'movement_direction' => 'incoming',
            'borrower_name' => 'Borrower Two',
            'status' => 'closed',
        ])->assertSessionHasErrors('status');

        $item->update(['status' => 'complete', 'completed_by' => $user->id, 'completed_at' => now()]);

        $this->actingAs($user)->put(route('land-titles.update', $title), [
            'branch_id' => $branch->id,
            'security_type' => 'land_title',
            'movement_direction' => 'incoming',
            'borrower_name' => 'Borrower Two',
            'status' => 'closed',
        ])->assertRedirect(route('land-titles.show', $title));

        $this->assertSame('closed', $title->fresh()->status);
        $this->assertNotNull($title->fresh()->closed_at);
    }

    public function test_security_milestone_reminder_is_sent_once_and_overdue_is_reported(): void
    {
        Notification::fake();
        $branch = Branch::create(['name' => 'Mbale', 'code' => 'MBL']);
        $user = $this->activeUser($branch, []);
        $title = LandTitle::create([
            'branch_id' => $branch->id,
            'reference_no' => 'SEC26100003',
            'security_type' => 'land_title',
            'movement_direction' => 'incoming',
            'borrower_name' => 'Borrower Three',
            'handled_by' => $user->id,
            'status' => 'in_progress',
        ]);
        $milestone = LandTitleMilestone::create([
            'land_title_id' => $title->id,
            'name' => 'Complete title search',
            'due_at' => now()->subDay(),
            'reminder_days_before' => 1,
            'status' => 'pending',
        ]);

        $this->artisan('kfms:security-reminders')->assertSuccessful();
        Notification::assertSentTo($user, SecurityMilestoneReminder::class);
        $this->assertDatabaseHas('land_title_milestone_reminder_logs', [
            'land_title_milestone_id' => $milestone->id,
            'reminder_type' => 'overdue',
            'status' => 'sent',
        ]);

        Notification::fake();
        $this->artisan('kfms:security-reminders')->assertSuccessful();
        Notification::assertNothingSent();
        $this->assertSame(1, LandTitleMilestoneReminderLog::where('land_title_milestone_id', $milestone->id)->count());
    }

    public function test_security_records_are_branch_scoped(): void
    {
        $branchA = Branch::create(['name' => 'Branch A', 'code' => 'BRA']);
        $branchB = Branch::create(['name' => 'Branch B', 'code' => 'BRB']);
        $userA = $this->activeUser($branchA, ['land-titles.index', 'land-titles.show']);
        $title = LandTitle::create([
            'branch_id' => $branchB->id,
            'reference_no' => 'SEC26100004',
            'borrower_name' => 'Private Borrower',
            'status' => 'pending',
        ]);

        $this->actingAs($userA)->get(route('land-titles.index'))->assertOk()->assertDontSee($title->reference_no);
        $this->actingAs($userA)->get(route('land-titles.show', $title))->assertForbidden();
    }

    private function activeUser(Branch $branch, array $permissions): User
    {
        $role = Role::findOrCreate('Securities Workflow Tester', 'web');
        foreach ($permissions as $permission) {
            $role->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        $user = User::factory()->create(['branch_id' => $branch->id]);
        $user->assignRole($role);
        StaffProfile::create([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'employment_status' => 'active',
        ]);

        return $user;
    }
}
