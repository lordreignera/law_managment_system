<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\CurrencyType;
use App\Models\PayrollComponent;
use App\Models\PayrollEmployeeProfile;
use App\Models\PayrollPeriod;
use App\Models\PayrollRule;
use App\Models\PayrollRun;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PayrollFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function payrollUser(): User
    {
        $user = User::factory()->create();
        StaffProfile::create(['user_id' => $user->id, 'employment_status' => 'active']);
        $permissions = collect([
            'payroll.dashboard',
            'payroll.components.index', 'payroll.components.create', 'payroll.components.store', 'payroll.components.edit', 'payroll.components.update', 'payroll.components.destroy',
            'payroll.rules.index', 'payroll.rules.create', 'payroll.rules.store', 'payroll.rules.edit', 'payroll.rules.update', 'payroll.rules.destroy',
            'payroll.employees.index', 'payroll.employees.create', 'payroll.employees.store', 'payroll.employees.show', 'payroll.employees.edit', 'payroll.employees.update',
            'payroll.periods.index', 'payroll.periods.create', 'payroll.periods.store', 'payroll.periods.show', 'payroll.periods.run.store',
            'payroll.runs.show', 'payroll.runs.calculate', 'payroll.runs.submit', 'payroll.runs.approve', 'payroll.runs.reject',
            'payroll.reports', 'payroll.runs.export', 'payroll.payslips.show', 'payroll.runs.payment-batch.store', 'payroll.runs.paid', 'payroll.runs.statutory.store',
        ])->map(fn ($name) => Permission::findOrCreate($name))->all();
        $user->givePermissionTo($permissions);

        return $user;
    }

    public function test_payroll_setup_screens_and_configurable_records_work(): void
    {
        $user = $this->payrollUser();
        $bank = Bank::create(['name' => 'Approved Payroll Bank']);
        $currency = CurrencyType::create(['name' => 'Uganda Shilling', 'iso_code' => 'UGX', 'symbol' => 'USh']);

        $this->actingAs($user);
        foreach (['payroll.dashboard', 'payroll.components.index', 'payroll.rules.index', 'payroll.employees.index', 'payroll.periods.index', 'payroll.reports'] as $route) {
            $this->get(route($route))->assertOk();
        }

        $this->post(route('payroll.components.store'), [
            'code' => 'BASIC',
            'name' => 'Basic Salary',
            'component_type' => 'earning',
            'calculation_method' => 'fixed',
            'calculation_basis' => 'basic_salary',
            'amount' => 0,
            'is_recurring' => 1,
            'is_active' => 1,
        ])->assertSessionHasNoErrors();
        $component = PayrollComponent::where('code', 'BASIC')->firstOrFail();

        $this->post(route('payroll.rules.store'), [
            'code' => 'NSSF-EMPLOYEE',
            'name' => 'Employee NSSF',
            'rule_type' => 'nssf',
            'calculation_method' => 'percentage',
            'rate' => 5,
            'effective_from' => '2026-01-01',
            'configuration' => json_encode(['basis' => 'gross_pay']),
            'is_active' => 1,
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('payroll_rules', ['code' => 'NSSF-EMPLOYEE', 'rate' => 5]);

        $employee = User::factory()->create(['name' => 'Payroll Staff']);
        StaffProfile::create(['user_id' => $employee->id, 'employment_status' => 'active', 'staff_no' => 'ST-KA-90001']);
        $this->post(route('payroll.employees.store'), [
            'user_id' => $employee->id,
            'tin' => 'TIN-001',
            'nssf_number' => 'NSSF-001',
            'tax_residency' => 'resident',
            'bank_id' => $bank->id,
            'payment_method' => 'bank',
            'account_name' => 'Payroll Staff',
            'account_number' => '123456789',
            'is_active' => 1,
            'base_salary' => 2500000,
            'currency_type_id' => $currency->id,
            'pay_frequency' => 'monthly',
            'salary_effective_from' => '2026-01-01',
            'component_ids' => [$component->id],
            'component_amounts' => [$component->id => 2500000],
        ])->assertSessionHasNoErrors();
        $profile = PayrollEmployeeProfile::where('user_id', $employee->id)->firstOrFail();
        $this->assertDatabaseHas('payroll_salary_components', ['payroll_component_id' => $component->id]);

        $this->post(route('payroll.periods.store'), [
            'code' => 'PAY-2026-01',
            'name' => 'January 2026 Payroll',
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-01-31',
            'payment_on' => '2026-02-15',
        ])->assertSessionHasNoErrors();
        $period = PayrollPeriod::where('code', 'PAY-2026-01')->firstOrFail();

        $this->post(route('payroll.periods.run.store', $period))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('payroll_runs', ['payroll_period_id' => $period->id, 'status' => 'draft']);
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => $component->getMorphClass(), 'auditable_id' => $component->id, 'event' => 'created']);
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => $profile->getMorphClass(), 'auditable_id' => $profile->id, 'event' => 'created']);
    }

    public function test_payroll_run_calculates_from_configured_components_and_requires_separate_approval(): void
    {
        $preparer = $this->payrollUser();
        $approver = $this->payrollUser();
        $bank = Bank::create(['name' => 'Payroll Approval Bank']);
        $currency = CurrencyType::create(['name' => 'Uganda Shilling', 'iso_code' => 'UGX', 'symbol' => 'USh']);
        $allowance = PayrollComponent::create([
            'code' => 'HOUSE', 'name' => 'Housing allowance', 'component_type' => 'earning',
            'calculation_method' => 'formula', 'calculation_basis' => 'basic_salary', 'formula' => 'base_salary * 0.1',
            'is_taxable' => true, 'is_active' => true,
        ]);
        $deduction = PayrollComponent::create([
            'code' => 'STAFF-DED', 'name' => 'Staff deduction', 'component_type' => 'deduction',
            'calculation_method' => 'percentage', 'calculation_basis' => 'gross_pay', 'rate' => 10,
            'is_active' => true,
        ]);
        PayrollRule::create([
            'code' => 'PAYE-5', 'name' => 'Configured PAYE', 'rule_type' => 'paye',
            'calculation_method' => 'percentage', 'rate' => 5, 'configuration' => ['basis' => 'taxable_pay'],
            'effective_from' => '2026-01-01', 'is_active' => true,
        ]);

        $employee = User::factory()->create(['name' => 'Calculated Payroll Staff']);
        StaffProfile::create(['user_id' => $employee->id, 'employment_status' => 'active', 'staff_no' => 'ST-KA-90002']);
        $this->actingAs($preparer);
        $this->post(route('payroll.employees.store'), [
            'user_id' => $employee->id,
            'tax_residency' => 'resident', 'bank_id' => $bank->id, 'payment_method' => 'bank',
            'is_active' => 1, 'base_salary' => 2500000, 'currency_type_id' => $currency->id,
            'pay_frequency' => 'monthly', 'salary_effective_from' => '2026-01-01',
            'component_ids' => [$allowance->id, $deduction->id],
        ])->assertSessionHasNoErrors();

        $this->post(route('payroll.periods.store'), [
            'code' => 'PAY-2026-02', 'name' => 'February 2026 Payroll',
            'starts_on' => '2026-02-01', 'ends_on' => '2026-02-28', 'payment_on' => '2026-03-10',
        ])->assertSessionHasNoErrors();
        $period = PayrollPeriod::where('code', 'PAY-2026-02')->firstOrFail();
        $this->post(route('payroll.periods.run.store', $period))->assertSessionHasNoErrors();
        $run = PayrollRun::where('payroll_period_id', $period->id)->firstOrFail();

        $this->post(route('payroll.runs.calculate', $run))->assertSessionHasNoErrors();
        $run->refresh();
        $this->assertSame('calculated', $run->status);
        $this->assertSame(1, $run->lines()->count());
        $line = $run->lines()->with('components')->firstOrFail();
        $this->assertSame(2750000.0, (float) $line->gross_pay);
        $this->assertSame(412500.0, (float) $line->employee_deductions);
        $this->assertSame(2337500.0, (float) $line->net_pay);
        $this->assertSame(4, $line->components()->count());

        $this->post(route('payroll.runs.submit', $run))->assertSessionHasNoErrors();
        $this->assertSame('awaiting_approval', $run->fresh()->status);
        $this->patch(route('payroll.runs.approve', $run), ['review_notes' => 'Self approval should be blocked.'])
            ->assertForbidden();

        $this->actingAs($approver);
        $this->patch(route('payroll.runs.approve', $run), ['review_notes' => 'Reviewed and approved.'])
            ->assertSessionHasNoErrors();
        $this->assertSame('approved', $run->fresh()->status);
        $this->assertSame('approved', $period->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['event' => 'approval.approved', 'auditable_type' => $run->getMorphClass(), 'auditable_id' => $run->id]);

        $this->get(route('payroll.payslips.show', $line->payslip))->assertOk();
        $this->get(route('payroll.runs.export', [$run, 'bank']))->assertDownload();
        $this->post(route('payroll.runs.payment-batch.store', $run), ['bank_id' => $bank->id])->assertSessionHasNoErrors();
        $this->patch(route('payroll.runs.paid', $run))->assertSessionHasNoErrors();
        $this->assertSame('paid', $run->fresh()->status);
        $this->post(route('payroll.runs.statutory.store', $run), [
            'submission_type' => 'paye', 'status' => 'filed', 'reference' => 'URA-001',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('payroll_statutory_submissions', ['payroll_run_id' => $run->id, 'submission_type' => 'paye', 'status' => 'filed']);
    }
}
