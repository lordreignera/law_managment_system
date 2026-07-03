<?php

namespace Tests\Feature;

use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DashboardRenderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_main_and_finance_dashboards_render(): void
    {
        $role = Role::findOrCreate('Dashboard Viewer');
        foreach (['dashboard', 'finance.dashboard'] as $permissionName) {
            $role->givePermissionTo(Permission::findOrCreate($permissionName));
        }

        $user = User::factory()->create();
        $user->assignRole($role);
        StaffProfile::create(['user_id' => $user->id, 'employment_status' => 'active']);

        $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $this->actingAs($user)->get(route('finance.dashboard'))->assertOk();
    }
}
