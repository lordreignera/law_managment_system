<?php

namespace Tests\Feature;

use App\Models\AccountClass;
use App\Models\ChartAccount;
use App\Models\StaffProfile;
use App\Models\User;
use Database\Seeders\AccountClassSeeder;
use Database\Seeders\ChartAccountSeeder;
use Database\Seeders\FinanceAccountMappingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ChartAccountCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_finance_user_can_manage_seeded_chart_of_accounts(): void
    {
        $this->seed([
            AccountClassSeeder::class,
            ChartAccountSeeder::class,
            FinanceAccountMappingSeeder::class,
        ]);

        $user = $this->financeUser();

        $this->assertDatabaseHas('chart_accounts', ['account_number' => '1311', 'name' => 'Stanbic Bank UGX']);
        $this->assertDatabaseHas('chart_accounts', ['account_number' => '5100', 'name' => 'Dues & Subscriptions']);
        $this->assertDatabaseHas('finance_account_mappings', ['module' => 'petty_cash', 'mapping_key' => 'cash_account']);

        $this->actingAs($user)
            ->get(route('finance.chart-accounts.index'))
            ->assertOk()
            ->assertSee('Legal Chart of Accounts')
            ->assertSee('Fixed Assets');

        $this->actingAs($user)
            ->get(route('finance.chart-accounts.index', ['search' => 'Stanbic Bank UGX']))
            ->assertOk()
            ->assertSee('Stanbic Bank UGX');

        $this->actingAs($user)
            ->get(route('finance.chart-accounts.index', ['search' => 'Dues']))
            ->assertOk()
            ->assertSee('Dues &amp; Subscriptions', false);

        $expenseClass = AccountClass::where('name', 'Expenses')->firstOrFail();
        $parent = ChartAccount::where('account_number', '5100')->firstOrFail();

        $this->actingAs($user)
            ->get(route('finance.chart-accounts.create', [
                'account_class_id' => $expenseClass->id,
            ]))
            ->assertOk()
            ->assertSee('value="6200"', false);

        $this->actingAs($user)
            ->get(route('finance.chart-accounts.create', [
                'account_class_id' => $expenseClass->id,
                'parent_id' => $parent->id,
            ]))
            ->assertOk()
            ->assertSee('value="5103"', false);

        $this->actingAs($user)
            ->post(route('finance.chart-accounts.store'), [
                'account_class_id' => $expenseClass->id,
                'parent_id' => $parent->id,
                'account_number' => '',
                'name' => 'Court Filing Fees',
                'account_type' => 'expense',
                'normal_balance' => 'debit',
                'is_postable' => '1',
                'is_active' => '1',
            ])
            ->assertSessionHas('status', 'Chart account created.');

        $account = ChartAccount::where('name', 'Court Filing Fees')->firstOrFail();
        $this->assertSame('5103', $account->account_number);
        $this->assertSame($parent->id, $account->parent_id);

        $this->actingAs($user)
            ->put(route('finance.chart-accounts.update', $account), [
                'account_class_id' => $expenseClass->id,
                'parent_id' => $parent->id,
                'account_number' => $account->account_number,
                'name' => 'Court Filing and Registry Fees',
                'account_type' => 'expense',
                'normal_balance' => 'debit',
                'is_postable' => '1',
                'is_active' => '1',
            ])
            ->assertSessionHas('status', 'Chart account updated.');

        $this->assertDatabaseHas('chart_accounts', [
            'id' => $account->id,
            'name' => 'Court Filing and Registry Fees',
        ]);

        $this->actingAs($user)
            ->delete(route('finance.chart-accounts.destroy', $parent))
            ->assertSessionHasErrors('account');

        $this->actingAs($user)
            ->get(route('finance.chart-accounts.export'))
            ->assertOk();

        $this->actingAs($user)
            ->delete(route('finance.chart-accounts.destroy', $account->fresh()))
            ->assertRedirect(route('finance.chart-accounts.index', absolute: false))
            ->assertSessionHas('status', 'Chart account deleted.');

        $this->assertDatabaseMissing('chart_accounts', ['id' => $account->id]);
    }

    private function financeUser(): User
    {
        $role = Role::findOrCreate('Accountant', 'web');

        foreach ([
            'finance.chart-accounts.index',
            'finance.chart-accounts.create',
            'finance.chart-accounts.store',
            'finance.chart-accounts.show',
            'finance.chart-accounts.edit',
            'finance.chart-accounts.update',
            'finance.chart-accounts.destroy',
            'finance.chart-accounts.export',
        ] as $permission) {
            $role->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        $user = User::factory()->create();
        $user->assignRole($role);
        StaffProfile::create([
            'user_id' => $user->id,
            'employment_status' => 'active',
        ]);

        return $user;
    }
}
