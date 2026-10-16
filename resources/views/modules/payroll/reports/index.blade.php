@extends('layouts.admin')

@section('title', 'Payroll Reports')
@section('page-title', 'Payroll Reports & Exports')

@section('content')
    <div class="kfms-stat-grid">@foreach($summary as $label => $value)<section class="kfms-card"><span class="kfms-card-label">{{ $label }}</span><strong class="kfms-stat">{{ is_numeric($value) && str_contains($label, 'Payroll') ? number_format((float)$value, 2) : number_format((float)$value) }}</strong></section>@endforeach</div>
    <section class="kfms-panel">
        <div class="kfms-panel-header"><div><h2>Payroll run reports</h2><span>Register, payslip, statutory, and approved bank exports are generated from the locked run snapshot.</span></div><a class="kfms-link-btn" href="{{ route('payroll.dashboard') }}">Payroll Dashboard</a></div>
        <form class="kfms-table-toolbar" method="GET" action="{{ route('payroll.reports') }}"><label><span>Status</span><select name="status"><option value="">All statuses</option>@foreach($statuses as $value => $label)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></label><div></div><div></div><button class="kfms-link-btn" type="submit">Apply</button></form>
        <div class="kfms-table-wrap"><table class="kfms-table"><thead><tr><th>Period</th><th>Run Status</th><th>Employees</th><th>Gross</th><th>Deductions</th><th>Net Pay</th><th>Exports</th></tr></thead><tbody>
            @forelse($runs as $run)<tr><td><strong>{{ $run->period?->name }}</strong><br><small>{{ $run->period?->code }}</small></td><td><span class="kfms-status">{{ $run->statusLabel() }}</span></td><td>{{ number_format((int)data_get($run->totals, 'employees', 0)) }}</td><td>{{ number_format((float)data_get($run->totals, 'gross_pay', 0), 2) }}</td><td>{{ number_format((float)data_get($run->totals, 'employee_deductions', 0), 2) }}</td><td><strong>{{ number_format((float)data_get($run->totals, 'net_pay', 0), 2) }}</strong></td><td><div class="kfms-table-actions"><a class="kfms-link-btn" href="{{ route('payroll.runs.show', $run) }}">Review</a><a class="kfms-link-btn" href="{{ route('payroll.runs.export', [$run, 'register']) }}">Register</a>@if(in_array($run->status, ['approved','paid'], true))<a class="kfms-link-btn" href="{{ route('payroll.runs.export', [$run, 'bank']) }}">Bank CSV</a>@endif</div></td></tr>@empty<tr><td colspan="7" class="kfms-empty">No calculated payroll runs are available.</td></tr>@endforelse
        </tbody></table></div>{{ $runs->links() }}
    </section>
@endsection
