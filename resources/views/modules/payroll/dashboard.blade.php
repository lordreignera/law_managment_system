@extends('layouts.admin')

@section('title', 'Payroll Dashboard')
@section('page-title', 'Payroll Dashboard')

@section('content')
    <section class="kfms-dashboard-hero">
        <div>
            <span>Restricted payroll control room</span>
            <h2>Configure, review, approve, and close payroll periods.</h2>
            <p>Payroll components and calculation rules are configurable so the firm can adopt its approved salary setup without changing the application.</p>
        </div>
        <div class="kfms-dashboard-hero-actions">
            @can('payroll.periods.create')
                <a class="kfms-btn" href="{{ route('payroll.periods.create') }}"><i class="mdi mdi-calendar-plus"></i> Open Period</a>
            @endcan
            @can('payroll.components.create')
                <a class="kfms-link-btn" href="{{ route('payroll.components.create') }}"><i class="mdi mdi-plus"></i> Add Component</a>
            @endcan
        </div>
    </section>

    <div class="kfms-stat-grid kfms-dashboard-kpis">
        @foreach ($stats as $label => $value)
            <section class="kfms-card"><span class="kfms-card-label">{{ $label }}</span><strong class="kfms-stat">{{ number_format($value) }}</strong></section>
        @endforeach
    </div>

    <div class="kfms-grid-two">
        <section class="kfms-panel">
            <div class="kfms-panel-header">
                <div><h2>Payroll Periods</h2><span>Period lifecycle and current run status</span></div>
                <a class="kfms-link-btn" href="{{ route('payroll.periods.index') }}">View all</a>
            </div>
            <div class="kfms-table-wrap"><table class="kfms-table"><thead><tr><th>Period</th><th>Dates</th><th>Status</th></tr></thead><tbody>
                @forelse ($periods as $period)
                    <tr><td><a href="{{ route('payroll.periods.show', $period) }}">{{ $period->name }}</a><br><small>{{ $period->code }}</small></td><td>{{ $period->starts_on?->format('d M Y') }} - {{ $period->ends_on?->format('d M Y') }}</td><td><span class="kfms-status">{{ $period->statusLabel() }}</span></td></tr>
                @empty
                    <tr><td colspan="3" class="kfms-empty">No payroll periods have been opened.</td></tr>
                @endforelse
            </tbody></table></div>
        </section>

        <section class="kfms-panel">
            <div class="kfms-panel-header">
                <div><h2>Active Components</h2><span>Approved earnings, deductions, and employer costs</span></div>
                <a class="kfms-link-btn" href="{{ route('payroll.components.index') }}">Manage</a>
            </div>
            <div class="kfms-action-list">
                @forelse ($components as $component)
                    <a href="{{ route('payroll.components.edit', $component) }}"><strong>{{ $component->name }}</strong><small>{{ $component->typeLabel() }} · {{ $component->methodLabel() }}</small></a>
                @empty
                    <span class="kfms-muted-text">No components configured yet.</span>
                @endforelse
            </div>
        </section>
    </div>
@endsection
