<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Department;
use App\Models\StaffProfile;
use App\Models\User;
use App\Notifications\StaffAccountApproved;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
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
        $role->givePermissionTo(Permission::findOrCreate('dashboard'));

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
        $role->givePermissionTo(Permission::findOrCreate('dashboard'));

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
        Notification::fake();

        $accessRole = Role::findOrCreate('Access Manager');
        $accessRole->givePermissionTo(Permission::findOrCreate('access.users.index'));
        $accessRole->givePermissionTo(Permission::findOrCreate('access.users.approve'));
        $accessRole->givePermissionTo(Permission::findOrCreate('access.approvals.index'));
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

        Notification::assertSentTo($user, StaffAccountApproved::class);
    }

    public function test_access_manager_can_add_approved_user_through_staff_flow(): void
    {
        $accessRole = Role::findOrCreate('Access Manager');
        $accessRole->givePermissionTo(Permission::findOrCreate('access.users.index'));
        $accessRole->givePermissionTo(Permission::findOrCreate('access.users.create'));
        $accessRole->givePermissionTo(Permission::findOrCreate('access.users.store'));
        $newUserRole = Role::findOrCreate('Accountant');
        $branch = Branch::create(['name' => 'Kampala', 'code' => 'KLA']);
        $department = Department::create(['name' => 'Finance', 'code' => 'FIN', 'branch_id' => $branch->id]);

        $admin = User::factory()->create();
        $admin->assignRole($accessRole);
        StaffProfile::create([
            'user_id' => $admin->id,
            'employment_status' => 'active',
        ]);

        $this
            ->actingAs($admin)
            ->get(route('access.users.index'))
            ->assertOk()
            ->assertSee('Add User')
            ->assertSee(route('access.users.create', absolute: false), false);

        $this
            ->actingAs($admin)
            ->get(route('access.users.create'))
            ->assertOk()
            ->assertSee('Add User')
            ->assertSee(route('access.users.store', absolute: false), false)
            ->assertSee('Approved Access')
            ->assertSee('data-password-toggle', false);

        $response = $this
            ->actingAs($admin)
            ->post(route('access.users.store'), [
                'name' => 'Finance User',
                'email' => 'finance-user@example.com',
                'password' => 'temporary123',
                'password_confirmation' => 'temporary123',
                'staff_no' => 'AC-001',
                'phone' => '+256 700 555666',
                'job_title' => 'Accountant',
                'branch_id' => $branch->id,
                'department_id' => $department->id,
                'joined_on' => now()->toDateString(),
                'role' => $newUserRole->name,
            ]);

        $response->assertRedirect(route('access.users.index', absolute: false));
        $response->assertSessionHas('status', 'Finance User registered and approved.');

        $created = User::where('email', 'finance-user@example.com')->firstOrFail();

        $this->assertTrue(Hash::check('temporary123', $created->password));
        $this->assertNotNull($created->email_verified_at);
        $this->assertTrue($created->hasRole($newUserRole->name));
        $this->assertSame($branch->id, $created->branch_id);
        $this->assertSame($department->id, $created->department_id);
        $this->assertSame('active', $created->staffProfile?->employment_status);
        $this->assertSame($newUserRole->name, $created->staffProfile?->requested_role);
    }

    public function test_access_manager_can_edit_user_identity_password_roles_and_direct_permissions(): void
    {
        $accessRole = Role::findOrCreate('Access Manager');
        $accessRole->givePermissionTo(Permission::findOrCreate('access.users.index'));
        $accessRole->givePermissionTo(Permission::findOrCreate('access.users.edit'));
        $accessRole->givePermissionTo(Permission::findOrCreate('access.users.update'));
        $accountantRole = Role::findOrCreate('Accountant');
        $recoveriesRole = Role::findOrCreate('Recoveries Manager');
        $directPermissionA = Permission::findOrCreate('recoveries.dashboard');
        $directPermissionB = Permission::findOrCreate('finance.dashboard');

        $admin = User::factory()->create();
        $admin->assignRole($accessRole);
        StaffProfile::create([
            'user_id' => $admin->id,
            'employment_status' => 'active',
        ]);

        $user = User::factory()->create([
            'name' => 'Old User',
            'email' => 'old-user@example.com',
            'password' => Hash::make('old-password123'),
        ]);
        StaffProfile::create([
            'user_id' => $user->id,
            'employment_status' => 'active',
            'requested_role' => $accountantRole->name,
        ]);
        $user->assignRole($accountantRole);

        $this
            ->actingAs($admin)
            ->get(route('access.users.edit', $user))
            ->assertOk()
            ->assertSee('Edit User Access')
            ->assertSee('data-selection-group="roles"', false)
            ->assertSee('data-selection-group="direct_permissions"', false)
            ->assertSee('kfms-tick-option', false)
            ->assertSee('data-permission-select="all"', false)
            ->assertSee('New Password');

        $response = $this
            ->actingAs($admin)
            ->put(route('access.users.update', $user), [
                'name' => 'Updated User',
                'email' => 'updated-user@example.com',
                'password' => 'new-password123',
                'password_confirmation' => 'new-password123',
                'employment_status' => 'active',
                'roles' => [$accountantRole->name, $recoveriesRole->name],
                'direct_permissions' => [$directPermissionA->name, $directPermissionB->name],
            ]);

        $response->assertRedirect(route('access.users.edit', $user, absolute: false));
        $response->assertSessionHas('status', 'Updated User updated.');

        $updated = $user->fresh();

        $this->assertSame('Updated User', $updated->name);
        $this->assertSame('updated-user@example.com', $updated->email);
        $this->assertTrue(Hash::check('new-password123', $updated->password));
        $this->assertTrue($updated->hasAllRoles([$accountantRole->name, $recoveriesRole->name]));
        $this->assertTrue($updated->hasDirectPermission($directPermissionA->name));
        $this->assertTrue($updated->hasDirectPermission($directPermissionB->name));
        $this->assertSame('active', $updated->staffProfile?->employment_status);
    }
}
