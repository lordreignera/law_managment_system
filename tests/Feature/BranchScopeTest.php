<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Client;
use App\Models\Matter;
use App\Models\RecoveryAccount;
use App\Models\RecoveryClient;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class BranchScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function activeUser(array $permissions, string $roleName, ?int $branchId = null): User
    {
        $role = Role::findOrCreate($roleName);
        foreach ($permissions as $permission) {
            $role->givePermissionTo(Permission::findOrCreate($permission));
        }

        $user = User::factory()->create(['branch_id' => $branchId]);
        $user->assignRole($role);
        StaffProfile::create([
            'user_id' => $user->id,
            'employment_status' => 'active',
        ]);

        return $user;
    }

    private function client(string $name, ?int $branchId): Client
    {
        return Client::create([
            'client_no' => 'CL-'.uniqid(),
            'name' => $name,
            'branch_id' => $branchId,
        ]);
    }

    public function test_branch_user_only_sees_own_branch_and_firmwide_clients(): void
    {
        $branchA = Branch::create(['name' => 'Branch A', 'code' => 'BRA']);
        $branchB = Branch::create(['name' => 'Branch B', 'code' => 'BRB']);

        $this->client('Alpha (A)', $branchA->id);
        $this->client('Bravo (B)', $branchB->id);
        $this->client('Firmwide', null);

        $user = $this->activeUser(['clients.index'], 'Advocate', $branchA->id);

        $visible = Client::forBranchOf($user)->pluck('name')->all();

        $this->assertContains('Alpha (A)', $visible);
        $this->assertContains('Firmwide', $visible);
        $this->assertNotContains('Bravo (B)', $visible);
    }

    public function test_managing_partner_sees_all_branches(): void
    {
        $branchA = Branch::create(['name' => 'Branch A', 'code' => 'BRA']);
        $branchB = Branch::create(['name' => 'Branch B', 'code' => 'BRB']);

        $this->client('Alpha (A)', $branchA->id);
        $this->client('Bravo (B)', $branchB->id);

        $partner = $this->activeUser(['clients.index'], 'Managing Partner', $branchA->id);

        $this->assertTrue($partner->canSeeAllBranches());
        $this->assertCount(2, Client::forBranchOf($partner)->get());
    }

    public function test_matters_and_recoveries_are_branch_scoped(): void
    {
        $branchA = Branch::create(['name' => 'Branch A', 'code' => 'BRA']);
        $branchB = Branch::create(['name' => 'Branch B', 'code' => 'BRB']);

        $clientA = $this->client('Alpha', $branchA->id);

        Matter::create([
            'client_id' => $clientA->id,
            'reference_no' => 'MT-A-'.uniqid(),
            'title' => 'Matter A',
            'status' => 'active',
            'branch_id' => $branchA->id,
        ]);
        Matter::create([
            'client_id' => $clientA->id,
            'reference_no' => 'MT-B-'.uniqid(),
            'title' => 'Matter B',
            'status' => 'active',
            'branch_id' => $branchB->id,
        ]);

        $recoveryClient = RecoveryClient::create(['name' => 'Bank']);
        RecoveryAccount::create([
            'recovery_client_id' => $recoveryClient->id,
            'account_number' => 'ACC-A',
            'debtor_name' => 'Debtor A',
            'branch_id' => $branchA->id,
        ]);
        RecoveryAccount::create([
            'recovery_client_id' => $recoveryClient->id,
            'account_number' => 'ACC-B',
            'debtor_name' => 'Debtor B',
            'branch_id' => $branchB->id,
        ]);

        $user = $this->activeUser(['matters.index', 'recoveries.index'], 'Advocate', $branchA->id);

        $this->assertSame(['Matter A'], Matter::forBranchOf($user)->pluck('title')->all());
        $this->assertSame(['Debtor A'], RecoveryAccount::forBranchOf($user)->pluck('debtor_name')->all());
    }
}
