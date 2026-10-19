<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\LandTitle;
use App\Models\LandTitleChecklistItem;
use App\Models\LandTitleMilestone;
use App\Models\LandTitleMilestoneReminderLog;
use App\Models\LandTitleMovement;
use App\Imports\LandTitlesImport;
use App\Models\StaffProfile;
use App\Models\User;
use App\Notifications\SecurityMilestoneReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Collection;
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
            'securities.create', 'securities.store', 'securities.show', 'securities.update',
            'securities.checklist.store', 'securities.checklist.update', 'securities.checklist.destroy',
            'securities.movements.store',
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

        $this->actingAs($user)->put(route('securities.update', $title), [
            'branch_id' => $branch->id,
            'security_type' => 'mortgage',
            'movement_direction' => 'outgoing',
            'borrower_name' => 'Borrower One',
            'title_number' => 'KLA/123/45',
            'status' => 'dispatched',
            'dispatched_at' => now()->format('Y-m-d\\TH:i'),
            'dispatched_to' => 'Kampala Zonal Office',
        ])->assertRedirect(route('securities.show', $title));

        $this->assertDatabaseHas('land_title_movements', [
            'land_title_id' => $title->id,
            'movement_type' => 'dispatched',
            'to_party' => 'Kampala Zonal Office',
        ]);

        $item = $title->checklistItems()->firstOrFail();
        $this->actingAs($user)->patch(route('securities.checklist.update', [$title, $item]), [
            'status' => 'complete',
        ])->assertRedirect();

        $this->assertSame('complete', $item->fresh()->status);
        $this->assertNotNull($item->fresh()->completed_at);
        $this->assertSame(1, LandTitleMovement::where('land_title_id', $title->id)->count());
    }

    public function test_security_records_can_be_closed_only_after_required_checklist_is_complete(): void
    {
        $branch = Branch::create(['name' => 'Mbarara', 'code' => 'MBA']);
        $user = $this->activeUser($branch, ['securities.update', 'securities.show', 'securities.checklist.update']);
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

        $this->actingAs($user)->put(route('securities.update', $title), [
            'branch_id' => $branch->id,
            'security_type' => 'land_title',
            'movement_direction' => 'incoming',
            'borrower_name' => 'Borrower Two',
            'status' => 'closed',
        ])->assertSessionHasErrors('status');

        $item->update(['status' => 'complete', 'completed_by' => $user->id, 'completed_at' => now()]);

        $this->actingAs($user)->put(route('securities.update', $title), [
            'branch_id' => $branch->id,
            'security_type' => 'land_title',
            'movement_direction' => 'incoming',
            'borrower_name' => 'Borrower Two',
            'status' => 'closed',
        ])->assertRedirect(route('securities.show', $title));

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
        $userA = $this->activeUser($branchA, ['securities.index', 'securities.show']);
        $title = LandTitle::create([
            'branch_id' => $branchB->id,
            'reference_no' => 'SEC26100004',
            'borrower_name' => 'Private Borrower',
            'status' => 'pending',
        ]);

        $this->actingAs($userA)->get(route('securities.index'))->assertOk()->assertDontSee($title->reference_no);
        $this->actingAs($userA)->get(route('securities.show', $title))->assertForbidden();
    }

    public function test_security_documents_follow_branch_visibility(): void
    {
        Storage::fake('local');
        $branchA = Branch::create(['name' => 'Security A', 'code' => 'SCA']);
        $branchB = Branch::create(['name' => 'Security B', 'code' => 'SCB']);
        $userA = $this->activeUser($branchA, []);
        $userB = $this->activeUser($branchB, []);
        $title = LandTitle::create([
            'branch_id' => $branchA->id,
            'reference_no' => 'SEC26100005',
            'borrower_name' => 'Document Borrower',
            'status' => 'pending',
        ]);
        $this->actingAs($userA);
        $attachment = $title->addAttachment(UploadedFile::fake()->create('security.pdf', 20, 'application/pdf'));

        $this->actingAs($userA)->get(route('attachments.download', $attachment))->assertOk();
        $this->actingAs($userB)->get(route('attachments.download', $attachment))->assertForbidden();
    }

    public function test_security_rejects_invalid_handler_and_date_order(): void
    {
        $branch = Branch::create(['name' => 'Validation', 'code' => 'VAL']);
        $user = $this->activeUser($branch, ['securities.create', 'securities.store']);
        $clientUser = User::factory()->create(['account_type' => 'client']);

        $this->actingAs($user)->from(route('securities.create'))->post(route('securities.store'), [
            'borrower_name' => 'Invalid Handler',
            'handled_by' => $clientUser->id,
            'instruction_date' => '2026-07-10',
            'received_at' => '2026-07-09T09:00',
            'status' => 'pending',
        ])->assertSessionHasErrors('handled_by');

        $this->actingAs($user)->from(route('securities.create'))->post(route('securities.store'), [
            'borrower_name' => 'Invalid Dates',
            'instruction_date' => '2026-07-10',
            'received_at' => '2026-07-09T09:00',
            'status' => 'pending',
        ])->assertSessionHasErrors('received_at');
    }

    public function test_security_import_rejects_unknown_master_data_instead_of_nulling_it(): void
    {
        $branch = Branch::create(['name' => 'Import Validation', 'code' => 'IMP']);
        $user = $this->activeUser($branch, []);
        $import = new LandTitlesImport($user);
        $import->collection(new Collection([
            new Collection([
                'borrower_name' => 'Unknown Bank Borrower',
                'bank' => 'Bank That Does Not Exist',
                'status' => 'pending',
            ]),
        ]));

        $this->assertSame(0, $import->imported);
        $this->assertSame(1, $import->skipped);
        $this->assertStringContainsString('was not found', $import->errors[0]);
        $this->assertDatabaseMissing('land_titles', ['borrower_name' => 'Unknown Bank Borrower']);
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
