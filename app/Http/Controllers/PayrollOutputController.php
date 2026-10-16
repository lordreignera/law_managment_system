<?php

namespace App\Http\Controllers;

use App\Models\PayrollPaymentBatch;
use App\Models\PayrollPayslip;
use App\Models\PayrollRun;
use App\Models\PayrollStatutorySubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayrollOutputController extends Controller
{
    public function reports(Request $request)
    {
        $runs = PayrollRun::with(['period', 'preparer', 'approver', 'paymentBatch'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('modules.payroll.reports.index', [
            'runs' => $runs,
            'statuses' => PayrollRun::STATUSES,
            'filters' => $request->only('status'),
            'summary' => [
                'Calculated Runs' => PayrollRun::whereIn('status', ['calculated', 'awaiting_approval', 'approved', 'paid'])->count(),
                'Approved Runs' => PayrollRun::whereIn('status', ['approved', 'paid'])->count(),
                'Paid Runs' => PayrollRun::where('status', 'paid')->count(),
                'Net Payroll' => (float) PayrollRun::whereIn('status', ['approved', 'paid'])->get()->sum(fn (PayrollRun $run) => (float) data_get($run->totals, 'net_pay', 0)),
            ],
        ]);
    }

    public function payslip(PayrollPayslip $payslip)
    {
        $payslip->load(['line.run.period', 'line.run.approver', 'line.employeeProfile.user', 'line.components']);
        abort_unless($payslip->line?->run, 404);

        return view('modules.payroll.payslips.show', compact('payslip'));
    }

    public function export(Request $request, PayrollRun $run, string $format): StreamedResponse
    {
        abort_unless(in_array($format, ['register', 'bank', 'payslips', 'statutory'], true), 404);
        abort_if($format === 'bank' && $run->status !== 'approved' && $run->status !== 'paid', 422, 'The bank file is available after payroll approval.');

        $run->load(['period', 'lines.employeeProfile.user', 'lines.employeeProfile.bank', 'lines.employeeProfile.bankBranch', 'lines.components', 'lines.payslip']);
        if ($format === 'bank' && $run->paymentBatch) {
            $run->paymentBatch->update(['status' => 'exported', 'exported_at' => now()]);
        }
        $filename = 'payroll-'.$format.'-'.Str::slug($run->period->code).'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($run, $format) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $this->headings($format));
            foreach ($run->lines as $line) {
                fputcsv($handle, $this->row($line, $format, $run));
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function createPaymentBatch(Request $request, PayrollRun $run)
    {
        abort_unless($run->status === 'approved', 422, 'Only an approved payroll run can be prepared for payment.');
        $data = $request->validate([
            'bank_id' => ['nullable', 'exists:banks,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $batch = PayrollPaymentBatch::updateOrCreate(
            ['payroll_run_id' => $run->id],
            [
                'reference' => 'PB-'.$run->period()->value('code').'-'.$run->id,
                'bank_id' => $data['bank_id'] ?? null,
                'status' => 'ready',
                'total_amount' => data_get($run->totals, 'net_pay', 0),
                'created_by' => $request->user()->id,
                'notes' => $data['notes'] ?? null,
            ]
        );

        return back()->with('status', 'Payment batch '.$batch->reference.' prepared.');
    }

    public function markPaid(Request $request, PayrollRun $run)
    {
        abort_unless($run->status === 'approved', 422, 'Only an approved payroll run can be marked paid.');
        abort_unless($run->paymentBatch, 422, 'Prepare the payment batch before recording payment.');

        $run->paymentBatch->update(['status' => 'paid', 'paid_at' => now()]);
        $run->update(['status' => 'paid', 'paid_at' => now()]);
        $run->period()->update(['status' => 'paid']);

        return back()->with('status', 'Payroll payment recorded.');
    }

    public function storeStatutory(Request $request, PayrollRun $run)
    {
        abort_unless(in_array($run->status, ['approved', 'paid'], true), 422, 'Statutory submissions can be recorded after payroll approval.');
        $data = $request->validate([
            'submission_type' => ['required', Rule::in(['paye', 'nssf', 'other'])],
            'status' => ['required', Rule::in(['draft', 'filed', 'accepted', 'rejected'])],
            'due_on' => ['nullable', 'date'],
            'filed_on' => ['nullable', 'date'],
            'reference' => ['nullable', 'string', 'max:120'],
            'receipt_number' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        if ($data['status'] !== 'draft') {
            $data['filed_by'] = $request->user()->id;
            $data['filed_on'] = $data['filed_on'] ?? today();
        }

        PayrollStatutorySubmission::updateOrCreate(
            ['payroll_run_id' => $run->id, 'submission_type' => $data['submission_type']],
            collect($data)->except('submission_type')->all()
        );

        return back()->with('status', strtoupper($data['submission_type']).' submission updated.');
    }

    private function headings(string $format): array
    {
        return match ($format) {
            'bank' => ['Employee Name', 'Staff No.', 'Bank', 'Branch', 'Account Name', 'Account Number', 'Net Pay', 'Payment Reference'],
            'payslips' => ['Payslip Number', 'Employee Name', 'Staff No.', 'Period', 'Gross Pay', 'Deductions', 'Net Pay'],
            'statutory' => ['Employee Name', 'Staff No.', 'TIN', 'NSSF Number', 'Taxable Pay', 'Employee Deductions', 'Employer Contributions'],
            default => ['Employee Name', 'Staff No.', 'Base Salary', 'Gross Pay', 'Taxable Pay', 'Employee Deductions', 'Employer Contributions', 'Net Pay'],
        };
    }

    private function row($line, string $format, PayrollRun $run): array
    {
        $profile = $line->employeeProfile;

        return match ($format) {
            'bank' => [$line->employee_name, $line->staff_no, $profile?->bank?->name, $profile?->bankBranch?->name, $profile?->account_name, $profile?->account_number, (float) $line->net_pay, 'PAY-'.$line->run_id.'-'.$line->id],
            'payslips' => [$line->payslip?->payslip_number ?: 'PS-'.$line->id, $line->employee_name, $line->staff_no, $run->period?->name, (float) $line->gross_pay, (float) $line->employee_deductions, (float) $line->net_pay],
            'statutory' => [$line->employee_name, $line->staff_no, $profile?->tin, $profile?->nssf_number, (float) $line->taxable_pay, (float) $line->employee_deductions, (float) $line->employer_contributions],
            default => [$line->employee_name, $line->staff_no, (float) $line->base_salary, (float) $line->gross_pay, (float) $line->taxable_pay, (float) $line->employee_deductions, (float) $line->employer_contributions, (float) $line->net_pay],
        };
    }
}
