<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Department;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AccessControlFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_pending_staff_are_redirected_to_pending_access_screen(): void
    {
        $role = Role::findOrCreate('Advocate');
        $role->givePermissionTo(Permission::findOrCreate('view dashboard'));

        $user = User::factory()->create();
        $user->assignRole($role);
        StaffProfile::create([
            'user_id' => $user->id,
            'employment_status' => 'pending',
            'requested_role' => $role->name,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertRedirect(route('access.pending', absolute: false));
    }

    public function test_module_permissions_are_enforced(): void
    {
        $role = Role::findOrCreate('Dashboard User');
        $role->givePermissionTo(Permission::findOrCreate('view dashboard'));

        $user = User::factory()->create();
        $user->assignRole($role);
        StaffProfile::create([
            'user_id' => $user->id,
            'employment_status' => 'active',
        ]);

        $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $this->actingAs($user)->get(route('clients.index'))->assertForbidden();
    }

    public function test_approval_activates_user_and_grants_requested_role(): void
    {
        $accessRole = Role::findOrCreate('Access Manager');
        $accessRole->givePermissionTo(Permission::findOrCreate('manage access control'));
        $requestedRole = Role::findOrCreate('Advocate');
        $correctedRole = Role::findOrCreate('Paralegal');
        $branch = Branch::create(['name' => 'Kampala', 'code' => 'KLA']);
        $department = Department::create(['name' => 'Litigation', 'code' => 'LIT', 'branch_id' => $branch->id]);

        $admin = User::factory()->create();
        $admin->assignRole($accessRole);
        StaffProfile::create([
            'user_id' => $admin->id,
            'employment_status' => 'active',
        ]);

        $user = User::factory()->create();
        StaffProfile::create([
            'user_id' => $user->id,
            'employment_status' => 'pending',
            'requested_role' => $requestedRole->name,
        ]);

        $response = $this
            ->actingAs($admin)
            ->from(route('access.approvals.index'))
            ->patch(route('access.users.approve', $user), [
                'phone' => '+256 700 222333',
                'job_title' => 'Legal Assistant',
                'branch_id' => $branch->id,
                'department_id' => $department->id,
                'requested_role' => $correctedRole->name,
            ]);

        $response->assertRedirect(route('access.approvals.index', absolute: false));
        $response->assertSessionHas('status', $user->name.' approved.');
        $this->assertFalse($user->fresh()->hasRole($requestedRole->name));
        $this->assertTrue($user->fresh()->hasRole($correctedRole->name));

        $profile = $user->staffProfile()->first();

        $this->assertSame('active', $profile->employment_status);
        $this->assertSame('+256 700 222333', $profile->phone);
        $this->assertSame('Legal Assistant', $profile->job_title);
        $this->assertSame($branch->id, $profile->branch_id);
        $this->assertSame($department->id, $profile->department_id);
        $this->assertSame($correctedRole->name, $profile->requested_role);
        $this->assertSame($branch->id, $user->fresh()->branch_id);
        $this->assertSame($department->id, $user->fresh()->department_id);
    }
}
