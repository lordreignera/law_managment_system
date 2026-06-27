<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\RecoveryAccount;
use App\Models\RecoveryClient;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RecoveryFlowTest extends TestCase
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

    public function test_manager_can_create_and_assign_a_recovery(): void
    {
        $branch = Branch::create(['name' => 'Branch A', 'code' => 'BRA']);
        $manager = $this->activeUser(['recoveries.index', 'recoveries.create', 'recoveries.store', 'recoveries.show'], 'Recoveries Manager', $branch->id);
        $officer = $this->activeUser([], 'Recovery Officer', $branch->id);
        $client = RecoveryClient::create(['name' => 'Stanbic Bank', 'code' => 'RC-1']);

        $response = $this->actingAs($manager)->post(route('recoveries.store'), [
            'recovery_client_id' => $client->id,
            'debtor_name' => 'John Debtor',
            'outstanding_amount' => 5000000,
            'currency' => 'UGX',
            'status' => 'active',
            'assigned_to' => $officer->id,
        ]);

        $account = RecoveryAccount::first();

        $this->assertNotNull($account);
        $this->assertSame('John Debtor', $account->debtor_name);
        $this->assertSame($officer->id, $account->assigned_to);
        $this->assertSame($manager->id, $account->assigned_by);
        $this->assertNotNull($account->assigned_at);
        // Branch defaults from the assigned officer's branch.
        $this->assertSame($branch->id, $account->branch_id);
        $response->assertRedirect(route('recoveries.show', $account));
    }

    public function test_officer_payment_updates_recovered_total(): void
    {
        $branch = Branch::create(['name' => 'Branch A', 'code' => 'BRA']);
        $officer = $this->activeUser(['recoveries.mine', 'recoveries.show', 'recoveries.activities.store'], 'Recovery Officer', $branch->id);
        $client = RecoveryClient::create(['name' => 'DFCU Bank', 'code' => 'RC-2']);

        $account = RecoveryAccount::create([
            'recovery_client_id' => $client->id,
            'debtor_name' => 'Jane Debtor',
            'outstanding_amount' => 1000000,
            'amount_recovered' => 0,
            'status' => 'active',
            'assigned_to' => $officer->id,
            'branch_id' => $branch->id,
        ]);

        $this->actingAs($officer)->post(route('recoveries.activities.store', $account), [
            'activity_type' => 'payment',
            'activity_at' => now()->format('Y-m-d\TH:i'),
            'amount_paid' => 250000,
            'notes' => 'Debtor paid cash at branch.',
        ])->assertRedirect(route('recoveries.show', $account));

        $account->refresh();

        $this->assertEquals(250000, (float) $account->amount_recovered);
        $this->assertDatabaseHas('recovery_activities', [
            'recovery_account_id' => $account->id,
            'activity_type' => 'payment',
            'user_id' => $officer->id,
        ]);
    }

    public function test_officer_only_sees_own_assigned_recoveries(): void
    {
        $branch = Branch::create(['name' => 'Branch A', 'code' => 'BRA']);
        $officer = $this->activeUser(['recoveries.mine'], 'Recovery Officer', $branch->id);
        $other = $this->activeUser([], 'Recovery Officer', $branch->id);
        $client = RecoveryClient::create(['name' => 'Centenary Bank', 'code' => 'RC-3']);

        $mine = RecoveryAccount::create([
            'recovery_client_id' => $client->id,
            'debtor_name' => 'Mine Debtor',
            'status' => 'active',
            'assigned_to' => $officer->id,
            'branch_id' => $branch->id,
        ]);
        RecoveryAccount::create([
            'recovery_client_id' => $client->id,
            'debtor_name' => 'Other Debtor',
            'status' => 'active',
            'assigned_to' => $other->id,
            'branch_id' => $branch->id,
        ]);

        $response = $this->actingAs($officer)->get(route('recoveries.mine'));

        $response->assertOk();
        $response->assertSee('Mine Debtor');
        $response->assertDontSee('Other Debtor');
    }

    public function test_manager_can_view_reports_and_export(): void
    {
        $branch = Branch::create(['name' => 'Branch A', 'code' => 'BRA']);
        $manager = $this->activeUser(['recoveries.reports', 'recoveries.export'], 'Recoveries Manager', $branch->id);
        $officer = $this->activeUser([], 'Recovery Officer', $branch->id);
        $client = RecoveryClient::create(['name' => 'Bank of Africa', 'code' => 'RC-4']);

        RecoveryAccount::create([
            'recovery_client_id' => $client->id,
            'debtor_name' => 'Report Debtor',
            'outstanding_amount' => 750000,
            'amount_recovered' => 100000,
            'status' => 'active',
            'assigned_to' => $officer->id,
            'branch_id' => $branch->id,
        ]);

        $this->actingAs($manager)->get(route('recoveries.reports'))
            ->assertOk()
            ->assertSee('Recovery Reports');

        $pdf = $this->actingAs($manager)->get(route('recoveries.export', ['type' => 'officers', 'format' => 'pdf']));
        $pdf->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('content-type'));

        $xlsx = $this->actingAs($manager)->get(route('recoveries.export', ['type' => 'clients', 'format' => 'xlsx']));
        $xlsx->assertOk();
    }
}
