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
            ->assertSee('Human resources control room')
            ->assertSee('Manage staff access, leave, and team records.')
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
            ->assertSee('Edit Staff Profile')
            ->assertSee('New Password')
            ->assertSee('data-password-toggle', false);

        $this->actingAs($hr)
            ->put(route('staff.update', $staff), [
                'name' => 'Norah Nakamatte',
                'email' => 'norah@example.test',
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

        $staff->refresh();

        $this->assertMatchesRegularExpression('/^ST-KA-\d{5}$/', $staff->staffProfile?->staff_no);
        $this->assertDatabaseHas('staff_profiles', [
            'user_id' => $staff->id,
            'staff_no' => $staff->staffProfile?->staff_no,
            'job_title' => 'HR Officer',
            'employment_status' => 'active',
        ]);
    }

    public function test_hr_can_register_new_staff(): void
    {
        Notification::fake();

        $branch = Branch::create(['name' => 'Kampala']);
        $department = Department::create(['name' => 'Human Resources', 'branch_id' => $branch->id]);
        Role::findOrCreate('Advocate');

        $hr = $this->activeUser(['staff.index', 'staff.create', 'staff.store', 'staff.show']);

        $this->actingAs($hr)
            ->get(route('staff.create'))
            ->assertOk()
            ->assertSee('New Staff Member');
        $this->actingAs($hr)
            ->get(route('staff.create'))
            ->assertSee('data-password-toggle', false);

        $response = $this->actingAs($hr)
            ->post(route('staff.store'), [
                'name' => 'New Advocate',
                'email' => 'advocate@example.test',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'phone' => '+256700000002',
                'job_title' => 'Advocate',
                'role' => 'Advocate',
                'branch_id' => $branch->id,
                'department_id' => $department->id,
                'joined_on' => '2026-07-09',
                'employment_status' => 'pending',
            ]);

        $staff = User::where('email', 'advocate@example.test')->firstOrFail();

        $response->assertRedirect(route('staff.show', $staff, absolute: false));
        $this->assertTrue($staff->hasRole('Advocate'));
        $this->assertNotNull($staff->email_verified_at);
        $this->assertMatchesRegularExpression('/^ST-KA-\d{5}$/', $staff->staffProfile?->staff_no);
        $this->assertDatabaseHas('staff_profiles', [
            'user_id' => $staff->id,
            'staff_no' => $staff->staffProfile?->staff_no,
            'phone' => '+256700000002',
            'job_title' => 'Advocate',
            'employment_status' => 'active',
            'requested_role' => 'Advocate',
        ]);
        Notification::assertSentTo($staff, StaffAccountApproved::class, function (StaffAccountApproved $notification) use ($staff) {
            $mail = $notification->toMail($staff)->toArray();

            $this->assertContains('Temporary password: password123', $mail['introLines']);

            return true;
        });
    }

    public function test_hr_can_reset_staff_password_and_role_from_edit_screen(): void
    {
        $branch = Branch::create(['name' => 'Kampala']);
        $department = Department::create(['name' => 'Finance', 'branch_id' => $branch->id]);
        Role::findOrCreate('Advocate');
        Role::findOrCreate('Accountant');

        $hr = $this->activeUser(['staff.show', 'staff.edit', 'staff.update']);
        $staff = User::factory()->create([
            'password' => Hash::make('old-password'),
        ]);
        $staff->assignRole('Advocate');
        StaffProfile::create([
            'user_id' => $staff->id,
            'employment_status' => 'active',
        ]);

        $this->actingAs($hr)
            ->put(route('staff.update', $staff), [
                'name' => $staff->name,
                'email' => $staff->email,
                'phone' => '+256700000004',
                'job_title' => 'Accountant',
                'branch_id' => $branch->id,
                'department_id' => $department->id,
                'employment_status' => 'active',
                'role' => 'Accountant',
                'password' => 'new-password123',
                'password_confirmation' => 'new-password123',
            ])
            ->assertRedirect(route('staff.show', $staff, absolute: false));

        $staff->refresh();

        $this->assertTrue(Hash::check('new-password123', $staff->password));
        $this->assertTrue($staff->hasRole('Accountant'));
        $this->assertFalse($staff->hasRole('Advocate'));
    }

    public function test_dashboard_created_staff_can_login_to_role_dashboard(): void
    {
        Notification::fake();

        $branch = Branch::create(['name' => 'Kampala']);
        $department = Department::create(['name' => 'Finance', 'branch_id' => $branch->id]);

        $accountantRole = Role::findOrCreate('Accountant');
        $accountantRole->givePermissionTo(Permission::findOrCreate('finance.dashboard'));

        $hr = $this->activeUser(['staff.create', 'staff.store', 'staff.show']);

        $this->actingAs($hr)
            ->post(route('staff.store'), [
                'name' => 'Finance User',
                'email' => 'finance-user@example.test',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'phone' => '+256700000003',
                'job_title' => 'Accountant',
                'role' => 'Accountant',
                'branch_id' => $branch->id,
                'department_id' => $department->id,
            ])
            ->assertSessionHasNoErrors();

        $staff = User::where('email', 'finance-user@example.test')->firstOrFail();

        $this->assertTrue($staff->hasRole('Accountant'));
        $this->assertTrue($staff->can('finance.dashboard'));
        Notification::assertSentTo($staff, StaffAccountApproved::class, function (StaffAccountApproved $notification) use ($staff) {
            $mail = $notification->toMail($staff)->toArray();

            $this->assertContains('Temporary password: password123', $mail['introLines']);

            return true;
        });

        $this->post('/logout');
        $this->assertGuest();

        $this->post('/login', [
            'email' => 'finance-user@example.test',
            'password' => 'password123',
        ])->assertRedirect(route('finance.dashboard', absolute: false));

        $this->assertAuthenticated();
    }
}
