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
