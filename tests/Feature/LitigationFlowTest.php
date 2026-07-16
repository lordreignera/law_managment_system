<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\CourtEvent;
use App\Models\Matter;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
