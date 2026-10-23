<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Matter;
use App\Models\PracticeArea;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class MatterDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_advocate_can_open_matter_dashboard(): void
    {
        $user = $this->activeUser(['dashboard', 'matters.dashboard', 'matters.index', 'matters.show'], 'Advocate');
        $practiceArea = PracticeArea::create(['name' => 'Commercial Litigation', 'is_active' => true]);
        $client = Client::create(['client_no' => 'CL-MATTER', 'name' => 'Demo Client']);

        $matter = Matter::create([
            'client_id' => $client->id,
            'practice_area_id' => $practiceArea->id,
            'reference_no' => 'MT-DASH-001',
            'title' => 'Matter dashboard validation',
            'status' => 'active',
            'opened_by' => $user->id,
            'opened_on' => now()->toDateString(),
            'privacy_status' => 'public',
            'description' => 'Validate matter dashboard render.',
        ]);
        $matter->assignments()->create([
            'user_id' => $user->id,
            'assignment_role' => 'partner',
            'assigned_on' => now()->toDateString(),
            'is_lead' => true,
        ]);

        $this->actingAs($user)
            ->get(route('matters.dashboard'))
            ->assertOk()
            ->assertSee('Matter control room')
            ->assertSee('Open, track, and move client matters through firm work.')
            ->assertSee('Matter Pipeline')
            ->assertSee('Practice Areas')
            ->assertSee('Matter dashboard validation')
            ->assertSee('Commercial Litigation');
    }

    public function test_advocate_login_lands_on_matter_dashboard(): void
    {
        $user = $this->activeUser(['dashboard', 'matters.dashboard'], 'Advocate');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('matters.dashboard'));
    }

    public function test_matter_can_be_edited_with_updated_team_and_next_action(): void
    {
        $user = $this->activeUser(['matters.show', 'matters.edit', 'matters.update'], 'Advocate');
        $newPartner = $this->activeUser([], 'Partner');
        $practiceArea = PracticeArea::create(['name' => 'Litigation', 'is_active' => true]);
        $client = Client::create(['client_no' => 'CL-EDIT', 'name' => 'Edit Client']);
        $matter = Matter::create([
            'client_id' => $client->id,
            'practice_area_id' => $practiceArea->id,
            'reference_no' => 'MT-EDIT-001',
            'title' => 'Original title',
            'status' => 'open',
            'opened_on' => now()->toDateString(),
            'privacy_status' => 'public',
            'description' => 'Original instructions',
        ]);
        $matter->assignments()->create([
            'user_id' => $user->id,
            'assignment_role' => 'partner',
            'assigned_on' => now()->toDateString(),
            'is_lead' => true,
        ]);

        $this->actingAs($user)->get(route('matters.show', $matter))
            ->assertOk()->assertSee(route('matters.edit', $matter), false);
        $this->actingAs($user)->get(route('matters.edit', $matter))
            ->assertOk()->assertSee('Original title')->assertSee('Update Matter');

        $this->actingAs($user)->put(route('matters.update', $matter), [
            'title' => 'Updated title',
            'practice_area_id' => $practiceArea->id,
            'opened_on' => now()->toDateString(),
            'status' => 'active',
            'privacy_status' => 'private',
            'description' => 'Updated instructions',
            'next_action' => 'File submissions',
            'next_action_due' => now()->addWeek()->toDateString(),
            'partner_ids' => [$newPartner->id],
            'associate_ids' => [$user->id],
        ])->assertRedirect(route('matters.show', $matter));

        $this->assertDatabaseHas('matters', [
            'id' => $matter->id,
            'client_id' => $client->id,
            'title' => 'Updated title',
            'status' => 'active',
            'next_action' => 'File submissions',
        ]);
        $this->assertDatabaseHas('matter_assignments', ['matter_id' => $matter->id, 'user_id' => $newPartner->id, 'assignment_role' => 'partner', 'is_lead' => true]);
        $this->assertDatabaseHas('matter_assignments', ['matter_id' => $matter->id, 'user_id' => $user->id, 'assignment_role' => 'associate', 'is_lead' => false]);
    }

    public function test_matter_must_be_closed_with_a_date_before_it_can_be_archived(): void
    {
        $user = $this->activeUser(['matters.update'], 'Matter Closer');
        $practiceArea = PracticeArea::create(['name' => 'Closure Practice', 'is_active' => true]);
        $client = Client::create(['client_no' => 'CL-CLOSE', 'name' => 'Closure Client']);
        $matter = Matter::create([
            'client_id' => $client->id,
            'practice_area_id' => $practiceArea->id,
            'reference_no' => 'MT-CLOSE-001',
            'title' => 'Matter to close',
            'status' => 'active',
            'opened_on' => now()->subDays(30)->toDateString(),
            'privacy_status' => 'public',
            'description' => 'Complete the assigned legal work.',
        ]);
        $matter->assignments()->create([
            'user_id' => $user->id,
            'assignment_role' => 'partner',
            'assigned_on' => now()->toDateString(),
            'is_lead' => true,
        ]);
        $payload = [
            'title' => $matter->title,
            'practice_area_id' => $practiceArea->id,
            'opened_on' => $matter->opened_on->toDateString(),
            'privacy_status' => 'public',
            'description' => $matter->description,
            'partner_ids' => [$user->id],
        ];

        $this->actingAs($user)->put(route('matters.update', $matter), $payload + ['status' => 'closed'])
            ->assertSessionHasErrors('closed_on');
        $this->put(route('matters.update', $matter), $payload + ['status' => 'archived', 'closed_on' => today()->toDateString()])
            ->assertSessionHasErrors('status');

        $this->put(route('matters.update', $matter), $payload + ['status' => 'closed', 'closed_on' => today()->toDateString()])
            ->assertRedirect(route('matters.show', $matter));
        $this->assertSame('closed', $matter->fresh()->status);
        $this->assertSame(today()->toDateString(), $matter->fresh()->closed_on->toDateString());

        $this->put(route('matters.update', $matter), $payload + ['status' => 'archived', 'closed_on' => today()->toDateString()])
            ->assertRedirect(route('matters.show', $matter));
        $this->assertSame('archived', $matter->fresh()->status);
        $this->assertSame(today()->toDateString(), $matter->fresh()->closed_on->toDateString());
    }

    public function test_matter_creation_rejects_prospects_and_inactive_clients(): void
    {
        $user = $this->activeUser(['matters.create', 'matters.store'], 'Matter Creator');
        $practiceArea = PracticeArea::create(['name' => 'Approved Instructions', 'is_active' => true]);
        $prospect = Client::create([
            'client_no' => 'CL-PROSPECT-MATTER',
            'name' => 'Unapproved Prospect',
            'status' => 'active',
            'is_prospect' => true,
        ]);
        $inactiveClient = Client::create([
            'client_no' => 'CL-INACTIVE-MATTER',
            'name' => 'Inactive Client',
            'status' => 'inactive',
            'is_prospect' => false,
        ]);

        $this->actingAs($user)->get(route('matters.create'))
            ->assertOk()
            ->assertDontSee($prospect->name)
            ->assertDontSee($inactiveClient->name);

        $payload = [
            'title' => 'Attempted new matter',
            'practice_area_id' => $practiceArea->id,
            'opened_on' => today()->toDateString(),
            'privacy_status' => 'public',
            'description' => 'This should not open before client approval.',
            'partner_ids' => [$user->id],
        ];

        $this->post(route('matters.store'), $payload + ['client_id' => $prospect->id])
            ->assertSessionHasErrors('client_id');
        $this->post(route('matters.store'), $payload + ['client_id' => $inactiveClient->id])
            ->assertSessionHasErrors('client_id');

        $this->assertDatabaseCount('matters', 0);
    }

    public function test_matter_register_workspace_instructions_and_billing_screens_render(): void
    {
        $user = $this->activeUser([
            'matters.index', 'matters.create', 'matters.show',
            'matters.instructions.show', 'matters.billing.show',
        ], 'Advocate');
        $client = Client::create(['client_no' => 'CL-SCREENS', 'name' => 'Screen Client']);
        $matter = Matter::create([
            'client_id' => $client->id,
            'reference_no' => 'MT-SCREENS-001',
            'title' => 'Screen coverage',
            'status' => 'open',
        ]);

        foreach (['matters.index', 'matters.create'] as $routeName) {
            $this->actingAs($user)->get(route($routeName))->assertOk();
        }
        foreach (['matters.show', 'matters.instructions.show', 'matters.billing.show'] as $routeName) {
            $this->actingAs($user)->get(route($routeName, $matter))->assertOk();
        }
    }

    public function test_matter_forms_render_role_aware_assignment_checklists(): void
    {
        $user = $this->activeUser(['matters.create'], 'Advocate');

        $this->actingAs($user)
            ->get(route('matters.create'))
            ->assertOk()
            ->assertSee('Select all partners')
            ->assertSee('Select all associates')
            ->assertSee('Search partners')
            ->assertSee('Search associates')
            ->assertSee('Select existing files')
            ->assertSee('Search existing client files to link')
            ->assertSee('Upload Documents')
            ->assertSee('name="documents[]"', false)
            ->assertSee('Initial Matter Status')
            ->assertSee('A matter is opened after the client intake and instruction are approved.')
            ->assertSee('Open')
            ->assertDontSee('name="status"', false)
            ->assertDontSee('Date Closed')
            ->assertSee($user->name)
            ->assertSee('Advocate')
            ->assertSee('data-check-dropdown', false);
    }

    private function activeUser(array $permissions, string $roleName): User
    {
        $role = Role::findOrCreate($roleName);

        foreach ($permissions as $permission) {
            $role->givePermissionTo(Permission::findOrCreate($permission));
        }

        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $user->assignRole($role);

        StaffProfile::create([
            'user_id' => $user->id,
            'employment_status' => 'active',
        ]);

        return $user;
    }
}
