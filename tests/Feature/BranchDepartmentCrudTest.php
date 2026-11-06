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

class BranchDepartmentCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('Super Admin'));
        StaffProfile::create(['user_id' => $user->id, 'employment_status' => 'active']);

        return $user;
    }

    private function branchUser(Branch $branch): User
    {
        $role = Role::findOrCreate('Branch Manager');
        $role->givePermissionTo([
            Permission::findOrCreate('branches.index'),
            Permission::findOrCreate('branches.update'),
        ]);

        $user = User::factory()->create(['branch_id' => $branch->id]);
        $user->assignRole($role);
        StaffProfile::create([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'employment_status' => 'active',
        ]);

        return $user;
    }

    public function test_branch_departments_can_be_created_viewed_updated_and_deleted(): void
    {
        $admin = $this->superAdmin();
        $branch = Branch::create(['name' => 'Mbale', 'code' => 'MBL', 'city' => 'Mbale', 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('branches.show', $branch))
            ->assertOk()
            ->assertSee('No departments have been added');

        $this->actingAs($admin)
            ->post(route('departments.store', $branch), [
                'name' => 'Mbale Litigation',
                'description' => 'Mbale legal work',
                'sort_order' => 2,
                'is_active' => '1',
            ])
            ->assertRedirect(route('branches.show', $branch, absolute: false));

        $department = Department::where('name', 'Mbale Litigation')->firstOrFail();
        $this->assertSame($branch->id, $department->branch_id);
        $this->assertNotEmpty($department->code);

        $this->actingAs($admin)
            ->get(route('departments.show', [$branch, $department]))
            ->assertOk()
            ->assertSee('Mbale Litigation');

        $this->actingAs($admin)
            ->put(route('departments.update', [$branch, $department]), [
                'name' => 'Mbale Dispute Resolution',
                'description' => 'Updated description',
                'sort_order' => 1,
                'is_active' => '1',
            ])
            ->assertRedirect(route('branches.show', $branch, absolute: false));

        $this->assertDatabaseHas('departments', [
            'id' => $department->id,
            'branch_id' => $branch->id,
            'name' => 'Mbale Dispute Resolution',
        ]);

        $this->actingAs($admin)
            ->delete(route('departments.destroy', [$branch, $department]))
            ->assertRedirect(route('branches.show', $branch, absolute: false));

        $this->assertDatabaseMissing('departments', ['id' => $department->id]);
    }

    public function test_branch_with_departments_cannot_be_deleted(): void
    {
        $admin = $this->superAdmin();
        $branch = Branch::create(['name' => 'Mbarara', 'code' => 'MBR', 'is_active' => true]);
        Department::create(['branch_id' => $branch->id, 'name' => 'Mbarara Finance', 'code' => 'M-FIN', 'is_active' => true]);

        $this->actingAs($admin)
            ->delete(route('branches.destroy', $branch))
            ->assertStatus(422);

        $this->assertDatabaseHas('branches', ['id' => $branch->id]);
    }

    public function test_branch_users_can_manage_only_their_own_branch_departments(): void
    {
        $ownBranch = Branch::create(['name' => 'Gulu', 'code' => 'GUL', 'is_active' => true]);
        $otherBranch = Branch::create(['name' => 'Jinja', 'code' => 'JIN', 'is_active' => true]);
        $user = $this->branchUser($ownBranch);

        $this->actingAs($user)
            ->get(route('branches.index'))
            ->assertOk()
            ->assertSee('Gulu')
            ->assertDontSee('Jinja');

        $this->actingAs($user)
            ->post(route('departments.store', $ownBranch), [
                'name' => 'Gulu Litigation',
                'is_active' => '1',
            ])
            ->assertRedirect(route('branches.show', $ownBranch, absolute: false));

        $this->actingAs($user)
            ->get(route('branches.show', $otherBranch))
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('departments.store', $otherBranch), [
                'name' => 'Jinja Finance',
                'is_active' => '1',
            ])
            ->assertForbidden();
    }
}
