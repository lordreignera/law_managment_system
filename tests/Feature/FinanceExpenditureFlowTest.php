<?php

namespace Tests\Feature;

use App\Models\AccountClass;
use App\Models\ChartAccount;
use App\Models\Client;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Matter;
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

    public function test_accountant_can_create_invoice_and_record_payment_from_finance_dashboard(): void
    {
        $accountant = $this->activeUser([
            'finance.dashboard',
            'finance.index',
            'finance.invoices.create',
            'finance.invoices.store',
            'finance.payments.create',
            'finance.payments.store',
        ], 'Accountant');
        $client = Client::create([
            'client_no' => 'CL-001',
            'client_type' => 'corporate',
            'organization_name' => 'Acme Traders',
            'status' => 'active',
        ]);
        $matter = Matter::create([
            'client_id' => $client->id,
            'opened_by' => $accountant->id,
            'reference_no' => 'MAT-001',
            'title' => 'Commercial advisory',
            'opened_on' => '2026-09-01',
            'status' => 'open',
        ]);
        $assetClass = AccountClass::create([
            'name' => 'Assets',
            'code_prefix' => '1',
            'normal_balance' => 'debit',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $bankAccount = ChartAccount::create([
            'account_class_id' => $assetClass->id,
            'account_number' => '1000',
            'name' => 'Main Bank',
            'account_type' => 'asset',
            'normal_balance' => 'debit',
            'level' => 1,
            'sort_order' => 1000,
            'is_postable' => true,
            'is_bank_account' => true,
            'is_active' => true,
        ]);

        $this->actingAs($accountant)
            ->get(route('finance.invoices.create', ['from' => 'finance-dashboard']))
            ->assertOk()
            ->assertSee('Back to Finance Dashboard')
            ->assertSee('Add Invoice');

        $this->actingAs($accountant)->post(route('finance.invoices.store'), [
            'client_id' => $client->id,
            'matter_id' => $matter->id,
            'invoice_date' => '2026-09-05',
            'due_date' => '2026-09-30',
            'subtotal' => '1,000,000',
            'tax' => '80,000',
            'status' => 'sent',
            'from' => 'finance-dashboard',
        ])->assertRedirect(route('finance.dashboard'));

        $invoice = Invoice::firstOrFail();
        $this->assertEqualsWithDelta(1080000, (float) $invoice->total, 0.001);
        $this->assertEqualsWithDelta(1080000, $invoice->balance, 0.001);

        $this->actingAs($accountant)
            ->get(route('finance.payments.create', ['invoice_id' => $invoice->id]))
            ->assertOk()
            ->assertSee('Record Payment');

        $this->actingAs($accountant)
            ->get(route('finance.payments.create', ['invoice_id' => $invoice->id, 'from' => 'finance-dashboard']))
            ->assertRedirect(route('finance.dashboard', ['payment_modal' => 1, 'invoice_id' => $invoice->id]));

        $this->actingAs($accountant)
            ->get(route('finance.dashboard', ['payment_modal' => 1, 'invoice_id' => $invoice->id]))
            ->assertOk()
            ->assertSee('Received Into')
            ->assertSee('Record Payment');

        $this->actingAs($accountant)->post(route('finance.payments.store'), [
            'invoice_id' => $invoice->id,
            'chart_account_id' => $bankAccount->id,
            'amount' => '200,000',
            'paid_on' => '2026-09-06',
            'payment_method' => 'Bank',
            'reference' => 'RCPT-200',
            'from' => 'finance-dashboard',
        ])->assertRedirect(route('finance.dashboard'));

        $invoice->refresh();

        $this->assertSame(1, InvoicePayment::count());
        $this->assertEqualsWithDelta(200000, (float) $invoice->paid_amount, 0.001);
        $this->assertEqualsWithDelta(880000, $invoice->balance, 0.001);
        $this->assertSame('part_paid', $invoice->status);
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
