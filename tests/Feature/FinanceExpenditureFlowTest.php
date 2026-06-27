<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\PettyCashTransaction;
use App\Models\Requisition;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class FinanceExpenditureFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function activeUser(array $permissions, string $roleName): User
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

    public function test_accountant_can_record_an_expense(): void
    {
        $category = ExpenseCategory::create(['name' => 'Stationery']);
        $accountant = $this->activeUser(['expenses.index', 'expenses.create', 'expenses.store', 'expenses.show'], 'Accountant');

        $this->actingAs($accountant)->post(route('expenses.store'), [
            'expense_category_id' => $category->id,
            'description' => 'Printer paper',
            'amount' => 120000,
            'payment_source' => 'bank',
            'spent_on' => '2026-06-27',
        ])->assertRedirect();

        $this->assertDatabaseHas('expenses', [
            'description' => 'Printer paper',
            'amount' => 120000,
            'recorded_by' => $accountant->id,
        ]);
    }

    public function test_petty_cash_balance_tracks_topups_and_disbursements(): void
    {
        $accountant = $this->activeUser(['petty-cash.index', 'petty-cash.create', 'petty-cash.store'], 'Accountant');

        $this->actingAs($accountant)->post(route('petty-cash.store'), [
            'type' => 'top_up',
            'description' => 'Initial float',
            'amount' => 500000,
            'transacted_on' => '2026-06-01',
        ])->assertRedirect();

        $this->actingAs($accountant)->post(route('petty-cash.store'), [
            'type' => 'disbursement',
            'description' => 'Office water',
            'amount' => 50000,
            'transacted_on' => '2026-06-05',
        ])->assertRedirect();

        $this->assertEqualsWithDelta(450000, PettyCashTransaction::balance(), 0.001);
    }

    public function test_disbursement_cannot_exceed_balance(): void
    {
        $accountant = $this->activeUser(['petty-cash.store'], 'Accountant');

        $response = $this->actingAs($accountant)->post(route('petty-cash.store'), [
            'type' => 'disbursement',
            'description' => 'Overspend',
            'amount' => 10000,
            'transacted_on' => '2026-06-05',
        ]);

        $response->assertSessionHasErrors('amount');
        $this->assertSame(0, PettyCashTransaction::count());
    }

    public function test_approving_a_requisition_creates_an_expenditure(): void
    {
        $staff = $this->activeUser(['requisitions.store'], 'Advocate');
        $approver = $this->activeUser(['requisitions.approve'], 'Accountant');

        $this->actingAs($staff)->post(route('requisitions.store'), [
            'purpose' => 'Court filing fees',
            'amount' => 75000,
        ])->assertRedirect();

        $requisition = Requisition::first();

        $this->actingAs($approver)
            ->patch(route('requisitions.approve', $requisition), ['review_notes' => 'OK'])
            ->assertRedirect();

        $this->assertDatabaseHas('expenses', [
            'requisition_id' => $requisition->id,
            'amount' => 75000,
            'description' => 'Court filing fees',
        ]);
        $this->assertSame(1, Expense::where('requisition_id', $requisition->id)->count());
    }
}
