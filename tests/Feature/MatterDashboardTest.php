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
