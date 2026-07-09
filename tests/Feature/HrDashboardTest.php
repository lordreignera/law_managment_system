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

class HrDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function activeUser(array $permissions, string $roleName = 'HR Manager'): User
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

    public function test_hr_manager_lands_on_hr_dashboard(): void
    {
        $hr = $this->activeUser(['dashboard', 'hr.dashboard', 'staff.index', 'leave.index']);

        $this->actingAs($hr)
            ->get(route('dashboard'))
            ->assertRedirect(route('hr.dashboard', absolute: false));

        $this->actingAs($hr)
            ->get(route('hr.dashboard'))
            ->assertOk()
            ->assertSee('HR Workspace')
            ->assertSee('Active Staff');
    }

    public function test_hr_can_update_staff_profile(): void
    {
        $branch = Branch::create(['name' => 'Kampala']);
        $department = Department::create(['name' => 'Human Resources', 'branch_id' => $branch->id]);

        $hr = $this->activeUser(['staff.index', 'staff.show', 'staff.edit', 'staff.update']);
        $staff = User::factory()->create([
            'name' => 'Original Name',
            'email' => 'staff@example.test',
        ]);
        StaffProfile::create([
            'user_id' => $staff->id,
            'employment_status' => 'active',
        ]);

        $this->actingAs($hr)
            ->get(route('staff.index'))
            ->assertOk()
            ->assertSee('Staff Register');

        $this->actingAs($hr)
            ->get(route('staff.show', $staff))
            ->assertOk()
            ->assertSee('Original Name');

        $this->actingAs($hr)
            ->get(route('staff.edit', $staff))
            ->assertOk()
            ->assertSee('Edit Staff Profile');

        $this->actingAs($hr)
            ->put(route('staff.update', $staff), [
                'name' => 'Norah Nakamatte',
                'email' => 'norah@example.test',
                'staff_no' => 'KCA-001',
                'phone' => '+256700000001',
                'job_title' => 'HR Officer',
                'branch_id' => $branch->id,
                'department_id' => $department->id,
                'joined_on' => '2026-07-08',
                'employment_status' => 'active',
            ])
            ->assertRedirect(route('staff.show', $staff, absolute: false));

        $this->assertDatabaseHas('users', [
            'id' => $staff->id,
            'name' => 'Norah Nakamatte',
            'email' => 'norah@example.test',
            'branch_id' => $branch->id,
            'department_id' => $department->id,
        ]);

        $this->assertDatabaseHas('staff_profiles', [
            'user_id' => $staff->id,
            'staff_no' => 'KCA-001',
            'job_title' => 'HR Officer',
            'employment_status' => 'active',
        ]);
    }

    public function test_hr_can_register_new_staff(): void
    {
        $branch = Branch::create(['name' => 'Kampala']);
        $department = Department::create(['name' => 'Human Resources', 'branch_id' => $branch->id]);
        Role::findOrCreate('Advocate');

        $hr = $this->activeUser(['staff.index', 'staff.create', 'staff.store', 'staff.show']);

        $this->actingAs($hr)
            ->get(route('staff.create'))
            ->assertOk()
            ->assertSee('New Staff Member');

        $response = $this->actingAs($hr)
            ->post(route('staff.store'), [
                'name' => 'New Advocate',
                'email' => 'advocate@example.test',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'staff_no' => 'KCA-002',
                'phone' => '+256700000002',
                'job_title' => 'Advocate',
                'role' => 'Advocate',
                'branch_id' => $branch->id,
                'department_id' => $department->id,
                'joined_on' => '2026-07-09',
                'employment_status' => 'active',
            ]);

        $staff = User::where('email', 'advocate@example.test')->firstOrFail();

        $response->assertRedirect(route('staff.show', $staff, absolute: false));
        $this->assertTrue($staff->hasRole('Advocate'));
        $this->assertDatabaseHas('staff_profiles', [
            'user_id' => $staff->id,
            'staff_no' => 'KCA-002',
            'phone' => '+256700000002',
            'job_title' => 'Advocate',
            'employment_status' => 'active',
            'requested_role' => 'Advocate',
        ]);
    }
}
