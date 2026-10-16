<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use App\Models\PayrollRun;
use App\Services\ApprovalService;
use App\Services\PayrollCalculator;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class PayrollRunController extends Controller
{
    public function show(PayrollRun $run)
    {
        $run->load([
            'period.creator',
            'preparer',
            'approver',
            'lines.employeeProfile.user',
            'lines.components',
            'lines.payslip',
            'approvals.requester',
            'approvals.approver',
            'paymentBatch',
            'statutorySubmissions',
        ]);

        return view('modules.payroll.runs.show', [
            'run' => $run,
            'pendingApproval' => $run->currentApproval(),
            'banks' => Bank::orderBy('name')->get(),
        ]);
    }

    public function calculate(PayrollRun $run, PayrollCalculator $calculator)
    {
        abort_unless(in_array($run->status, ['draft', 'calculated'], true), 422, 'Only a draft or calculated payroll run can be recalculated.');

        try {
            $calculator->calculate($run);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['formula' => $exception->getMessage()]);
        }

        return redirect()->route('payroll.runs.show', $run)->with('status', 'Payroll run calculated from the active employee and component setup.');
    }

    public function submit(Request $request, PayrollRun $run, ApprovalService $approvals)
    {
        abort_unless(in_array($run->status, ['calculated', 'draft'], true), 422, 'Calculate the payroll run before submitting it.');
        abort_if($run->lines()->doesntExist(), 422, 'A payroll run must contain at least one employee.');

        if ($run->status === 'draft') {
            abort(422, 'Calculate the payroll run before submitting it.');
        }

        $approvals->submit($run, $request->user());
        $run->update(['status' => 'awaiting_approval']);
        $run->period()->update(['status' => 'awaiting_approval']);

        return redirect()->route('payroll.runs.show', $run)->with('status', 'Payroll run submitted for approval.');
    }

    public function approve(Request $request, PayrollRun $run, ApprovalService $approvals)
    {
        $this->decide($request, $run, $approvals, 'approved');

        return back()->with('status', 'Payroll run approved.');
    }

    public function reject(Request $request, PayrollRun $run, ApprovalService $approvals)
    {
        $this->decide($request, $run, $approvals, 'rejected');

        return back()->with('status', 'Payroll run rejected and returned for correction.');
    }

    protected function decide(Request $request, PayrollRun $run, ApprovalService $approvals, string $decision): void
    {
        abort_unless($run->status === 'awaiting_approval', 422, 'Only a payroll run awaiting approval can be reviewed.');
        abort_if($run->prepared_by === $request->user()->id, 403, 'The person who prepared payroll cannot approve the same run.');

        $data = $request->validate([
            'review_notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $approval = $run->currentApproval();
        abort_unless($approval, 422, 'This payroll run has no pending approval request.');

        if ($decision === 'approved') {
            $approvals->approve($approval, $request->user(), $data['review_notes'] ?? null);
            $run->update([
                'status' => 'approved',
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
            ]);
            $run->period()->update(['status' => 'approved']);
        } else {
            $approvals->reject($approval, $request->user(), $data['review_notes'] ?? null);
            $run->update(['status' => 'calculated', 'approved_by' => null, 'approved_at' => null]);
            $run->period()->update(['status' => 'processing']);
        }
    }
}
