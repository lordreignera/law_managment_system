@extends('layouts.admin')

@section('title', 'Payroll Run · '.$run->period->name)
@section('page-title', 'Payroll Run')

@section('content')
    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <span>{{ $run->period->code }} · {{ $run->period->name }}</span>
                <h2>Payroll run review</h2>
                <p>{{ $run->period->starts_on?->format('d M Y') }} to {{ $run->period->ends_on?->format('d M Y') }} · payment {{ $run->period->payment_on?->format('d M Y') ?: 'date not set' }}</p>
            </div>
            <div class="kfms-toolbar-actions">
                <span class="kfms-status">{{ $run->statusLabel() }}</span>
                <a class="kfms-link-btn" href="{{ route('payroll.periods.show', $run->period) }}">Back to period</a>
            </div>
        </div>

        @if(session('status'))<div class="kfms-alert">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="kfms-alert kfms-alert-danger"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

        <div class="kfms-detail-grid">
            <div><span>Prepared By</span><strong>{{ $run->preparer?->name ?: '-' }}</strong></div>
            <div><span>Calculated At</span><strong>{{ $run->calculated_at?->format('d M Y H:i') ?: 'Not calculated' }}</strong></div>
            <div><span>Approval</span><strong>{{ $run->approvalStatus() ?: 'Not submitted' }}</strong></div>
            <div><span>Approved By</span><strong>{{ $run->approver?->name ?: '-' }}</strong></div>
        </div>

        <div class="kfms-toolbar-actions" style="margin-top:1rem;flex-wrap:wrap">
            @if(in_array($run->status, ['draft', 'calculated'], true))
                <form method="POST" action="{{ route('payroll.runs.calculate', $run) }}">@csrf<button class="kfms-btn" type="submit">{{ $run->status === 'calculated' ? 'Recalculate' : 'Calculate payroll' }}</button></form>
            @endif
            @if($run->status === 'calculated')
                <form method="POST" action="{{ route('payroll.runs.submit', $run) }}">@csrf<button class="kfms-link-btn" type="submit">Submit for approval</button></form>
            @endif
        </div>
    </section>

    @php($totals = $run->totals ?: [])
    <div class="kfms-stat-grid">
        <section class="kfms-card"><span class="kfms-card-label">Employees</span><strong class="kfms-stat">{{ number_format((int)($totals['employees'] ?? $run->lines->count())) }}</strong></section>
        <section class="kfms-card"><span class="kfms-card-label">Gross Pay</span><strong class="kfms-stat">{{ number_format((float)($totals['gross_pay'] ?? 0), 2) }}</strong></section>
        <section class="kfms-card"><span class="kfms-card-label">Deductions</span><strong class="kfms-stat">{{ number_format((float)($totals['employee_deductions'] ?? 0), 2) }}</strong></section>
        <section class="kfms-card"><span class="kfms-card-label">Net Pay</span><strong class="kfms-stat">{{ number_format((float)($totals['net_pay'] ?? 0), 2) }}</strong></section>
    </div>

    @if($run->status === 'awaiting_approval' && $pendingApproval && auth()->id() !== $run->prepared_by && auth()->user()->can('payroll.runs.approve'))
        <section class="kfms-panel">
            <div class="kfms-panel-header"><div><h2>Approval decision</h2><span>Review the immutable calculation snapshot before deciding.</span></div></div>
            <form method="POST" action="{{ route('payroll.runs.approve', $run) }}" class="kfms-form-grid">
                @csrf @method('PATCH')
                <label class="kfms-field kfms-field-wide"><span>Review notes</span><textarea name="review_notes" rows="3" placeholder="Add approval notes or exceptions">{{ old('review_notes') }}</textarea></label>
                <div class="kfms-toolbar-actions kfms-field-wide"><button class="kfms-btn" type="submit">Approve payroll</button></div>
            </form>
            <form method="POST" action="{{ route('payroll.runs.reject', $run) }}" class="kfms-form-grid" style="margin-top:.75rem">
                @csrf @method('PATCH')
                <label class="kfms-field kfms-field-wide"><span>Reason for return</span><textarea name="review_notes" rows="2" required placeholder="Explain what must be corrected">{{ old('review_notes') }}</textarea></label>
                <div class="kfms-toolbar-actions kfms-field-wide"><button class="kfms-link-btn" type="submit">Return for correction</button></div>
            </form>
        </section>
    @endif

    <section class="kfms-panel">
        <div class="kfms-panel-header"><div><h2>Payroll lines</h2><span>Each line is a period snapshot of the employee's approved setup.</span></div></div>
        <div class="kfms-table-wrap"><table class="kfms-table"><thead><tr><th>Employee</th><th>Base</th><th>Gross</th><th>Taxable</th><th>Deductions</th><th>Employer Cost</th><th>Net Pay</th></tr></thead><tbody>
            @forelse($run->lines as $line)
                <tr><td><strong>{{ $line->employee_name }}</strong><br><small>{{ $line->staff_no ?: $line->employeeProfile?->user?->email ?: '-' }}</small></td><td>{{ number_format((float)$line->base_salary, 2) }}</td><td>{{ number_format((float)$line->gross_pay, 2) }}</td><td>{{ number_format((float)$line->taxable_pay, 2) }}</td><td>{{ number_format((float)$line->employee_deductions, 2) }}</td><td>{{ number_format((float)$line->employer_contributions, 2) }}</td><td><strong>{{ number_format((float)$line->net_pay, 2) }}</strong><br>@if($line->payslip)<a class="kfms-link-btn" href="{{ route('payroll.payslips.show', $line->payslip) }}">Payslip</a>@endif</td></tr>
                <tr><td colspan="7"><details><summary>Component breakdown</summary><div class="kfms-table-wrap" style="margin-top:.5rem"><table class="kfms-table"><thead><tr><th>Component</th><th>Type</th><th>Amount</th><th>Rate</th><th>Basis</th></tr></thead><tbody>@foreach($line->components as $component)<tr><td>{{ $component->name }}<br><small>{{ $component->code }}</small></td><td>{{ str($component->component_type)->headline() }}</td><td>{{ number_format((float)$component->amount, 2) }}</td><td>{{ $component->rate !== null ? number_format((float)$component->rate, 4).'%' : '-' }}</td><td>{{ data_get($component->metadata, 'basis') ?: '-' }}</td></tr>@endforeach</tbody></table></div></details></td></tr>
            @empty
                <tr><td colspan="7" class="kfms-empty">Calculate this run to create payroll lines.</td></tr>
            @endforelse
        </tbody></table></div>
    </section>

    <section class="kfms-panel">
        <div class="kfms-panel-header"><div><h2>Approval history</h2><span>Every decision is recorded for the payroll audit trail.</span></div></div>
        <div class="kfms-table-wrap"><table class="kfms-table"><thead><tr><th>Round</th><th>Status</th><th>Requested By</th><th>Decided By</th><th>Decision Time</th><th>Comment</th></tr></thead><tbody>@forelse($run->approvals as $approval)<tr><td>{{ $approval->round }} / {{ $approval->level }}</td><td>{{ str($approval->status)->headline() }}</td><td>{{ $approval->requester?->name ?: '-' }}</td><td>{{ $approval->approver?->name ?: '-' }}</td><td>{{ $approval->decided_at?->format('d M Y H:i') ?: '-' }}</td><td>{{ $approval->comment ?: '-' }}</td></tr>@empty<tr><td colspan="6" class="kfms-empty">No approval request has been opened.</td></tr>@endforelse</tbody></table></div>
    </section>

    @if($run->status === 'approved' || $run->status === 'paid')
        <section class="kfms-panel">
            <div class="kfms-panel-header"><div><h2>Payment and statutory filing</h2><span>Record the approved payment batch and the firm's PAYE/NSSF filing references.</span></div><div class="kfms-toolbar-actions"><a class="kfms-link-btn" href="{{ route('payroll.runs.export', [$run, 'register']) }}">Register CSV</a><a class="kfms-link-btn" href="{{ route('payroll.runs.export', [$run, 'payslips']) }}">Payslips CSV</a><a class="kfms-link-btn" href="{{ route('payroll.runs.export', [$run, 'statutory']) }}">Statutory CSV</a><a class="kfms-link-btn" href="{{ route('payroll.runs.export', [$run, 'bank']) }}">Bank CSV</a></div></div>
            <div class="kfms-detail-grid"><div><span>Payment Batch</span><strong>{{ $run->paymentBatch?->reference ?: 'Not prepared' }}</strong></div><div><span>Payment Status</span><strong>{{ $run->paymentBatch?->status ? str($run->paymentBatch->status)->headline() : 'Not prepared' }}</strong></div><div><span>PAYE</span><strong>{{ optional($run->statutorySubmissions->firstWhere('submission_type', 'paye'))->status ? str($run->statutorySubmissions->firstWhere('submission_type', 'paye')->status)->headline() : 'Not recorded' }}</strong></div><div><span>NSSF</span><strong>{{ optional($run->statutorySubmissions->firstWhere('submission_type', 'nssf'))->status ? str($run->statutorySubmissions->firstWhere('submission_type', 'nssf')->status)->headline() : 'Not recorded' }}</strong></div></div>
            @if($run->status === 'approved')
                <form method="POST" action="{{ route('payroll.runs.payment-batch.store', $run) }}" class="kfms-form-grid" style="margin-top:1rem"><input type="hidden" name="_token" value="{{ csrf_token() }}"><label class="kfms-field"><span>Payment bank</span><select name="bank_id"><option value="">Select payment bank</option>@foreach($banks as $bank)<option value="{{ $bank->id }}" @selected($run->paymentBatch?->bank_id === $bank->id)>{{ $bank->name }}</option>@endforeach</select></label><label class="kfms-field"><span>Batch notes</span><input name="notes" value="{{ $run->paymentBatch?->notes }}" placeholder="Optional bank instructions"></label><div class="kfms-toolbar-actions kfms-field-wide"><button class="kfms-btn" type="submit">Prepare payment batch</button></div></form>
                @if($run->paymentBatch)<form method="POST" action="{{ route('payroll.runs.paid', $run) }}" style="margin-top:.75rem">@csrf @method('PATCH')<button class="kfms-link-btn" type="submit">Record payment</button></form>@endif
            @endif
            <form method="POST" action="{{ route('payroll.runs.statutory.store', $run) }}" class="kfms-form-grid" style="margin-top:1rem"><input type="hidden" name="_token" value="{{ csrf_token() }}"><label class="kfms-field"><span>Submission</span><select name="submission_type"><option value="paye">PAYE</option><option value="nssf">NSSF</option><option value="other">Other statutory</option></select></label><label class="kfms-field"><span>Status</span><select name="status"><option value="draft">Draft</option><option value="filed">Filed</option><option value="accepted">Accepted</option><option value="rejected">Rejected</option></select></label><label class="kfms-field"><span>Reference</span><input name="reference"></label><label class="kfms-field"><span>Receipt Number</span><input name="receipt_number"></label><label class="kfms-field kfms-field-wide"><span>Notes</span><textarea name="notes" rows="2"></textarea></label><div class="kfms-toolbar-actions kfms-field-wide"><button class="kfms-link-btn" type="submit">Save statutory submission</button></div></form>
        </section>
    @endif
@endsection
