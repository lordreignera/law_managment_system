<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Branch;
use App\Models\CourtEvent;
use App\Models\Matter;
use App\Models\StaffProfile;
use App\Models\User;
use App\Exports\LitigationExport;
use App\Exports\MattersExport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class LitigationFlowTest extends TestCase
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

    private function matter(): Matter
    {
        $client = Client::create([
            'client_no' => 'CL-'.uniqid(),
            'name' => 'Acme Holdings',
        ]);

        return Matter::create([
            'client_id' => $client->id,
            'reference_no' => 'MT-'.uniqid(),
            'title' => 'Acme v. State',
            'status' => 'active',
        ]);
    }

    public function test_advocate_can_schedule_a_court_event(): void
    {
        $matter = $this->matter();
        $advocate = $this->activeUser(['litigation.index', 'litigation.create', 'litigation.store', 'litigation.show'], 'Advocate');

        $this->actingAs($advocate)->post(route('litigation.store'), [
            'matter_id' => $matter->id,
            'event_type' => 'hearing',
            'court_name' => 'High Court',
            'case_number' => 'HCT-001-2026',
            'status' => 'scheduled',
            'starts_at' => now()->addDays(3)->format('Y-m-d\TH:i'),
        ])->assertRedirect();

        $this->assertDatabaseHas('court_events', [
            'matter_id' => $matter->id,
            'case_number' => 'HCT-001-2026',
            'event_type' => 'hearing',
            'status' => 'scheduled',
        ]);
    }

    public function test_first_court_event_moves_an_open_matter_into_active_work(): void
    {
        $client = Client::create([
            'client_no' => 'CL-'.uniqid(),
            'name' => 'New Litigation Client',
        ]);
        $matter = Matter::create([
            'client_id' => $client->id,
            'reference_no' => 'MT-'.uniqid(),
            'title' => 'New Court Matter',
            'status' => 'open',
        ]);
        $advocate = $this->activeUser(['litigation.store'], 'Advocate');

        $this->actingAs($advocate)->post(route('litigation.store'), [
            'matter_id' => $matter->id,
            'event_type' => 'filing_deadline',
            'court_name' => 'High Court',
            'status' => 'scheduled',
            'starts_at' => now()->addDays(3)->format('Y-m-d\\TH:i'),
        ])->assertRedirect();

        $this->assertSame('active', $matter->fresh()->status);
        $this->assertDatabaseHas('court_events', ['matter_id' => $matter->id, 'status' => 'scheduled']);
    }

    public function test_new_court_events_cannot_be_scheduled_for_closed_matters(): void
    {
        $matter = $this->matter();
        $matter->update(['status' => 'closed']);
        $advocate = $this->activeUser(['litigation.store'], 'Advocate');

        $this->actingAs($advocate)->post(route('litigation.store'), [
            'matter_id' => $matter->id,
            'event_type' => 'hearing',
            'court_name' => 'High Court',
            'status' => 'scheduled',
            'starts_at' => now()->addDays(3)->format('Y-m-d\\TH:i'),
        ])->assertSessionHasErrors('matter_id');

        $this->assertDatabaseMissing('court_events', ['matter_id' => $matter->id, 'event_type' => 'hearing']);
    }

    public function test_court_event_requires_a_court_selection_or_name(): void
    {
        $matter = $this->matter();
        $advocate = $this->activeUser(['litigation.store'], 'Advocate');

        $this->actingAs($advocate)->post(route('litigation.store'), [
            'matter_id' => $matter->id,
            'event_type' => 'hearing',
            'status' => 'scheduled',
            'starts_at' => now()->addDays(3)->format('Y-m-d\TH:i'),
        ])->assertSessionHasErrors('court_name');

        $this->assertDatabaseMissing('court_events', [
            'matter_id' => $matter->id,
            'event_type' => 'hearing',
        ]);
    }

    public function test_outcome_recording_updates_status_and_next_step(): void
    {
        $matter = $this->matter();
        $advocate = $this->activeUser(['litigation.outcome'], 'Advocate');

        $event = CourtEvent::create([
            'matter_id' => $matter->id,
            'event_type' => 'mention',
            'status' => 'scheduled',
            'starts_at' => now()->addDay(),
        ]);

        $this->actingAs($advocate)->patch(route('litigation.outcome', $event), [
            'status' => 'adjourned',
            'outcome' => 'Adjourned for hearing',
            'next_step' => 'File submissions',
            'next_step_due' => now()->addWeek()->format('Y-m-d'),
        ])->assertRedirect();

        $this->assertDatabaseHas('court_events', [
            'id' => $event->id,
            'status' => 'adjourned',
            'outcome' => 'Adjourned for hearing',
            'next_step' => 'File submissions',
        ]);
    }

    public function test_cause_list_summarises_upcoming_events(): void
    {
        $matter = $this->matter();
        $advocate = $this->activeUser(['litigation.index'], 'Advocate');

        CourtEvent::create([
            'matter_id' => $matter->id,
            'event_type' => 'hearing',
            'status' => 'scheduled',
            'starts_at' => now()->subDay(),
        ]);

        CourtEvent::create([
            'matter_id' => $matter->id,
            'event_type' => 'mention',
            'status' => 'completed',
            'starts_at' => now()->subWeek(),
        ]);

        $response = $this->actingAs($advocate)->get(route('litigation.index'));

        $response->assertOk();
        $response->assertViewHas('summary', function ($summary) {
            return $summary['Overdue'] === 1 && $summary['Completed'] === 1;
        });
    }

    public function test_litigation_lifecycle_actions_filter_export_and_use_staff_only_advocates(): void
    {
        $matter = $this->matter();
        $advocate = $this->activeUser([
            'litigation.dashboard',
            'litigation.index',
            'litigation.create',
            'litigation.export',
        ], 'Litigation Officer');
        $advocate->update(['name' => 'Active Litigation Advocate']);

        User::factory()->create([
            'name' => 'Portal Client User',
            'email' => 'portal.client@example.test',
            'account_type' => 'client',
        ]);

        CourtEvent::create([
            'matter_id' => $matter->id,
            'assigned_to' => $advocate->id,
            'event_type' => 'taxation',
            'case_number' => 'TAX-001-2026',
            'status' => 'scheduled',
            'starts_at' => now()->addDays(4),
        ]);

        CourtEvent::create([
            'matter_id' => $matter->id,
            'assigned_to' => $advocate->id,
            'event_type' => 'judgment',
            'case_number' => 'JUD-001-2026',
            'status' => 'completed',
            'starts_at' => now()->subDay(),
        ]);

        $this->actingAs($advocate)
            ->get(route('litigation.dashboard'))
            ->assertOk()
            ->assertSee('Add Hearing')
            ->assertSee('Add Judgment')
            ->assertSee('Add Taxation')
            ->assertSee('Track Execution')
            ->assertSee(route('litigation.index', ['stage' => 'taxation_execution']), false)
            ->assertSee(route('litigation.export', ['stage' => 'taxation_execution']), false);

        $this->actingAs($advocate)
            ->get(route('litigation.index', ['stage' => 'taxation_execution']))
            ->assertOk()
            ->assertSee('TAX-001-2026')
            ->assertDontSee('JUD-001-2026');

        $this->actingAs($advocate)
            ->get(route('litigation.export', ['stage' => 'taxation_execution']))
            ->assertOk();

        $this->actingAs($advocate)
            ->get(route('litigation.create', ['event_type' => 'taxation']))
            ->assertOk()
            ->assertSee('value="taxation" selected', false)
            ->assertSee('Active Litigation Advocate')
            ->assertDontSee('Portal Client User');
    }

    public function test_litigation_dashboard_uses_module_hero_design(): void
    {
        $advocate = $this->activeUser([
            'litigation.dashboard',
            'litigation.index',
            'litigation.create',
            'litigation.export',
        ], 'Litigation Officer');

        $this->actingAs($advocate)
            ->get(route('litigation.dashboard'))
            ->assertOk()
            ->assertSee('Litigation control room')
            ->assertSee('Track court work, deadlines, outcomes, and next steps.')
            ->assertSee('Schedule Court Event')
            ->assertSee('Litigation Lifecycle')
            ->assertSee('kfms-litigation-dashboard-grid', false)
            ->assertSee('kfms-litigation-stage-button', false)
            ->assertSee('aria-label="Matter Opening &amp; Planning: 0. Approved matters are opened, assigned, and prepared for legal work."', false)
            ->assertSee('data-tooltip="Approved matters are opened, assigned, and prepared for legal work."', false);
    }

    public function test_lifecycle_counts_match_visible_branch_events(): void
    {
        $branchA = Branch::create(['name' => 'Litigation A', 'code' => 'LTA']);
        $branchB = Branch::create(['name' => 'Litigation B', 'code' => 'LTB']);
        $advocate = $this->activeUser(['litigation.dashboard'], 'Advocate');
        $advocate->update(['branch_id' => $branchA->id]);

        $ownMatter = $this->matter();
        $ownMatter->update(['branch_id' => $branchA->id]);
        $otherMatter = $this->matter();
        $otherMatter->update(['branch_id' => $branchB->id]);

        foreach ([$ownMatter, $otherMatter] as $matter) {
            CourtEvent::create(['matter_id' => $matter->id, 'event_type' => 'hearing', 'status' => 'scheduled', 'starts_at' => now()->addDay()]);
            CourtEvent::create(['matter_id' => $matter->id, 'event_type' => 'judgment', 'status' => 'completed', 'starts_at' => now()->subDay()]);
            CourtEvent::create(['matter_id' => $matter->id, 'event_type' => 'taxation', 'status' => 'scheduled', 'starts_at' => now()->addDays(2)]);
        }

        $this->actingAs($advocate)->get(route('litigation.dashboard'))
            ->assertOk()
            ->assertViewHas('lifecycle', function ($stages) {
                $counts = collect($stages)->pluck('count', 'stage');

                return $counts['Filing, Service & Court Process'] === 1
                    && $counts['Judgment / Ruling'] === 1
                    && $counts['Taxation & Execution'] === 1;
            });
    }

    public function test_court_event_show_and_edit_screens_respect_outcome_permission(): void
    {
        $matter = $this->matter();
        $viewer = $this->activeUser(['litigation.show', 'litigation.edit'], 'Court Viewer');
        $event = CourtEvent::create([
            'matter_id' => $matter->id,
            'event_type' => 'hearing',
            'status' => 'scheduled',
            'starts_at' => now()->addDay(),
        ]);
        $this->actingAs($viewer)->get(route('litigation.show', $event))
            ->assertOk()->assertSee($matter->reference_no)->assertDontSee('Record Outcome');
        $this->actingAs($viewer)->get(route('litigation.edit', $event))
            ->assertOk()->assertSee('Save Changes');
    }

    public function test_court_documents_are_only_downloadable_from_a_visible_branch(): void
    {
        Storage::fake('local');
        $branchA = Branch::create(['name' => 'Document A', 'code' => 'DCA']);
        $branchB = Branch::create(['name' => 'Document B', 'code' => 'DCB']);
        $matter = $this->matter();
        $matter->update(['branch_id' => $branchA->id]);
        $event = CourtEvent::create([
            'matter_id' => $matter->id,
            'event_type' => 'filing_deadline',
            'status' => 'scheduled',
            'starts_at' => now()->addDay(),
        ]);
        Storage::disk('local')->put('court/notice.txt', 'Court notice');
        $attachment = $event->attachments()->create([
            'disk' => 'local',
            'path' => 'court/notice.txt',
            'original_name' => 'notice.txt',
        ]);
        $localUser = $this->activeUser([], 'Local Advocate');
        $localUser->update(['branch_id' => $branchA->id]);
        $otherUser = $this->activeUser([], 'Other Advocate');
        $otherUser->update(['branch_id' => $branchB->id]);

        $this->actingAs($localUser)->get(route('attachments.download', $attachment))->assertOk();
        $this->actingAs($otherUser)->get(route('attachments.download', $attachment))->assertForbidden();
    }

    public function test_private_matter_and_court_work_are_visible_only_to_its_team(): void
    {
        $permissions = ['matters.index', 'matters.show', 'litigation.index', 'litigation.show'];
        $owner = $this->activeUser($permissions, 'Private Matter Owner');
        $teammate = $this->activeUser($permissions, 'Private Matter Teammate');
        $outsider = $this->activeUser($permissions, 'Private Matter Outsider');
        $matter = $this->matter();
        $matter->update(['privacy_status' => 'private', 'opened_by' => $owner->id]);
        $matter->assignments()->create([
            'user_id' => $teammate->id,
            'assignment_role' => 'associate',
            'assigned_on' => today(),
        ]);
        $event = CourtEvent::create([
            'matter_id' => $matter->id,
            'event_type' => 'hearing',
            'status' => 'scheduled',
            'starts_at' => now()->addDay(),
        ]);
        Storage::fake('local');
        Storage::disk('local')->put('private/notice.txt', 'Private court notice');
        $attachment = $event->attachments()->create([
            'disk' => 'local',
            'path' => 'private/notice.txt',
            'original_name' => 'notice.txt',
        ]);

        foreach ([$owner, $teammate] as $visibleUser) {
            $this->actingAs($visibleUser)->get(route('matters.show', $matter))->assertOk();
            $this->actingAs($visibleUser)->get(route('litigation.show', $event))->assertOk();
        }

        $this->actingAs($outsider)->get(route('matters.index'))->assertOk()->assertDontSee($matter->reference_no);
        $this->actingAs($outsider)->get(route('litigation.index'))->assertOk()->assertDontSee($matter->reference_no);
        $this->actingAs($outsider)->get(route('matters.show', $matter))->assertForbidden();
        $this->actingAs($outsider)->get(route('litigation.show', $event))->assertNotFound();
        $this->actingAs($outsider)->get(route('attachments.download', $attachment))->assertForbidden();
        $this->actingAs($teammate)->get(route('attachments.download', $attachment))->assertOk();
        $this->assertSame(0, (new MattersExport($outsider))->query()->count());
        $this->assertSame(0, (new LitigationExport([], $outsider))->query()->count());
    }

    public function test_court_reminder_command_runs(): void
    {
        $matter = $this->matter();

        CourtEvent::create([
            'matter_id' => $matter->id,
            'event_type' => 'hearing',
            'status' => 'scheduled',
            'starts_at' => now()->addDay(),
        ]);

        $this->artisan('kfms:court-reminders')->assertExitCode(0);
    }
}
