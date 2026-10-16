@extends('layouts.admin')

@section('title', 'Payslip '.$payslip->payslip_number)
@section('page-title', 'Payslip')

@section('content')
    <section class="kfms-panel payroll-payslip">
        <div class="kfms-panel-header"><div><span>{{ $payslip->payslip_number }}</span><h2>{{ $payslip->line->employee_name }}</h2><p>{{ $payslip->line->run->period->name }} · {{ $payslip->line->run->period->starts_on?->format('d M Y') }} to {{ $payslip->line->run->period->ends_on?->format('d M Y') }}</p></div><div class="kfms-toolbar-actions"><button class="kfms-link-btn" type="button" onclick="window.print()">Print</button><a class="kfms-link-btn" href="{{ route('payroll.runs.show', $payslip->line->run) }}">Back to run</a></div></div>
        <div class="kfms-detail-grid"><div><span>Staff Number</span><strong>{{ $payslip->line->staff_no ?: '-' }}</strong></div><div><span>Approved By</span><strong>{{ $payslip->line->run->approver?->name ?: '-' }}</strong></div><div><span>Issued</span><strong>{{ $payslip->issued_at?->format('d M Y H:i') ?: '-' }}</strong></div><div><span>Status</span><strong>{{ str($payslip->status)->headline() }}</strong></div></div>
        <div class="kfms-table-wrap"><table class="kfms-table"><thead><tr><th>Component</th><th>Type</th><th>Amount</th></tr></thead><tbody>@foreach($payslip->line->components as $component)<tr><td>{{ $component->name }}<br><small>{{ $component->code }}</small></td><td>{{ str($component->component_type)->headline() }}</td><td>{{ number_format((float)$component->amount, 2) }}</td></tr>@endforeach</tbody><tfoot><tr><th colspan="2">Gross Pay</th><th>{{ number_format((float)$payslip->line->gross_pay, 2) }}</th></tr><tr><th colspan="2">Employee Deductions</th><th>{{ number_format((float)$payslip->line->employee_deductions, 2) }}</th></tr><tr><th colspan="2">Net Pay</th><th>{{ number_format((float)$payslip->line->net_pay, 2) }}</th></tr></tfoot></table></div>
    </section>
@endsection
