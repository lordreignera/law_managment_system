<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientPortalAccount;
use App\Models\CompanySetting;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class UserGuideAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        CompanySetting::query()->firstOrCreate(['id' => 1], CompanySetting::defaults());
    }

    private function staffUserWithPermissions(array $permissions, string $roleName = 'Staff User'): User
    {
        $role = Role::findOrCreate($roleName);

        foreach ($permissions as $permission) {
            $role->givePermissionTo(Permission::findOrCreate($permission));
        }

        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole($role);

        StaffProfile::create([
            'user_id' => $user->id,
            'employment_status' => 'active',
        ]);

        return $user;
    }

    public function test_staff_user_can_access_user_guide_without_module_permission(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        StaffProfile::create([
            'user_id' => $user->id,
            'employment_status' => 'active',
        ]);

        $this->actingAs($user)
            ->get(route('help.user-guide'))
            ->assertOk()
            ->assertSee('Workspace Guide')
            ->assertSee('First Steps')
            ->assertDontSee('Letters & Opinions Guide', false);
    }

    public function test_hr_user_sees_hr_guide_without_unrelated_module_descriptions(): void
    {
        $user = $this->staffUserWithPermissions(['hr.dashboard', 'staff.index', 'leave.index'], 'HR Manager');

        $this->actingAs($user)
            ->get(route('help.user-guide'))
            ->assertOk()
            ->assertSee('Human Resources Guide')
            ->assertSee('Human Resources Flow')
            ->assertSee('admin/assets/images/guides/hr-dashboard.svg')
            ->assertSeeInOrder([
                'Human Resources Flow',
                'admin/assets/images/guides/hr-dashboard.svg',
                'Use New Staff to create approved staff accounts from the dashboard.',
            ])
            ->assertSee('Download PDF')
            ->assertSee('href="'.e(route('help.user-guide.download')).'"', false)
            ->assertSee('href="'.e(route('hr.dashboard')).'"', false)
            ->assertSee('Use New Staff to create approved staff accounts from the dashboard.')
            ->assertDontSee('Finance Flow')
            ->assertDontSee('admin/assets/images/guides/finance-dashboard.svg')
            ->assertDontSee('Recoveries Manager Flow')
            ->assertDontSee('Securities Flow');
    }

    public function test_accountant_sees_finance_guide_without_hr_descriptions(): void
    {
        $user = $this->staffUserWithPermissions(['finance.dashboard', 'finance.index', 'finance.payments.create'], 'Accountant');

        $this->actingAs($user)
            ->get(route('help.user-guide'))
            ->assertOk()
            ->assertSee('Finance Guide')
            ->assertSee('Finance Flow')
            ->assertSee('admin/assets/images/guides/finance-dashboard.svg')
            ->assertSee('href="'.e(route('finance.dashboard')).'"', false)
            ->assertSee('Record payments against invoices')
            ->assertDontSee('Human Resources Flow')
            ->assertDontSee('admin/assets/images/guides/hr-dashboard.svg')
            ->assertDontSee('Securities Flow');
    }

    public function test_advocate_sees_matter_guide_with_dashboard_preview(): void
    {
        $user = $this->staffUserWithPermissions(['matters.dashboard', 'matters.index'], 'Advocate');

        $this->actingAs($user)
            ->get(route('help.user-guide'))
            ->assertOk()
            ->assertSee('Matter Guide')
            ->assertSee('Matter Dashboard')
            ->assertSee('admin/assets/images/guides/matter-dashboard.svg')
            ->assertSeeInOrder([
                'Matter Flow',
                'admin/assets/images/guides/matter-dashboard.svg',
                'Create a matter from the approved client or Matter Management.',
            ])
            ->assertSee('Pipeline, active files, responsible teams, and recent matters.')
            ->assertSee('href="'.e(route('matters.dashboard')).'"', false)
            ->assertDontSee('Finance Flow');
    }

    public function test_role_specific_guide_can_be_downloaded_as_pdf(): void
    {
        $user = $this->staffUserWithPermissions(['hr.dashboard', 'staff.index'], 'HR Manager');

        $this->actingAs($user)
            ->get(route('help.user-guide.download'))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_administrator_can_still_see_full_system_guide(): void
    {
        $user = $this->staffUserWithPermissions([], 'Administrator');

        $this->actingAs($user)
            ->get(route('help.user-guide'))
            ->assertOk()
            ->assertSee('System User Guide')
            ->assertSee('Finance Guide')
            ->assertSee('Human Resources Guide')
            ->assertSee('Securities Guide');
    }

    public function test_client_user_can_access_client_portal_guide(): void
    {
        $client = Client::create([
            'client_no' => 'CL-HELP',
            'client_type' => 'individual',
            'name' => 'Guide Client',
            'email' => 'guide.client@example.test',
            'status' => 'active',
        ]);

        $user = User::factory()->create([
            'email' => 'guide.client@example.test',
            'account_type' => 'client',
            'email_verified_at' => now(),
        ]);

        ClientPortalAccount::create([
            'client_id' => $client->id,
            'user_id' => $user->id,
            'registered_email' => $client->email,
            'verified_at' => now(),
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('client.help'))
            ->assertOk()
            ->assertSee('Client Portal Guide')
            ->assertSee('Messages');
    }
}
