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
