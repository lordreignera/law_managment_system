@extends('layouts.admin')

@section('title', 'Payroll Components')
@section('page-title', 'Payroll Components')

@section('content')
    <div class="kfms-stat-grid">
        @foreach ($summary as $label => $value)
            <section class="kfms-card"><span class="kfms-card-label">{{ $label }}</span><strong class="kfms-stat">{{ number_format($value) }}</strong></section>
        @endforeach
    </div>

    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div><h2>Payroll Components</h2><span>Define the firm's earnings, deductions, taxes, and employer contributions.</span></div>
            <div class="kfms-toolbar-actions"><a class="kfms-link-btn" href="{{ route('payroll.dashboard') }}">Payroll Dashboard</a><a class="kfms-btn" href="{{ route('payroll.components.create') }}"><i class="mdi mdi-plus"></i> Add Component</a></div>
        </div>
        @if (session('status'))<div class="kfms-alert">{{ session('status') }}</div>@endif
        @if ($errors->any())<div class="kfms-alert kfms-alert-danger">{{ $errors->first() }}</div>@endif

        <form class="kfms-table-toolbar" method="GET" action="{{ route('payroll.components.index') }}">
            <label class="kfms-search-box"><i class="mdi mdi-magnify"></i><input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search component name or code"></label>
            <label><span>Type</span><select name="component_type"><option value="">All Types</option>@foreach ($types as $value => $label)<option value="{{ $value }}" @selected(($filters['component_type'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></label>
            <label><span>Status</span><select name="status"><option value="">All</option><option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option><option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactive</option></select></label>
            <div class="kfms-toolbar-actions"><button class="kfms-link-btn" type="submit">Apply</button><a class="kfms-link-btn" href="{{ route('payroll.components.index') }}">Reset</a></div>
        </form>

        <div class="kfms-table-wrap"><table class="kfms-table"><thead><tr><th>Code</th><th>Component</th><th>Type</th><th>Calculation</th><th>Flags</th><th>Status</th><th>Actions</th></tr></thead><tbody>
            @forelse ($components as $component)
                <tr><td>{{ $component->code }}</td><td><strong>{{ $component->name }}</strong><br><small>{{ $component->description ?: 'No description' }}</small></td><td>{{ $component->typeLabel() }}</td><td>{{ $component->methodLabel() }}<br><small>{{ str($component->calculation_basis)->headline() }} · {{ $component->calculation_method === 'percentage' ? number_format((float) $component->rate, 4).'%' : number_format((float) $component->amount, 2) }}</small></td><td>@if($component->is_taxable)<span class="kfms-status">Taxable</span>@endif @if($component->is_nssf_liable)<span class="kfms-status">NSSF</span>@endif @if($component->is_employer_cost)<span class="kfms-status">Employer</span>@endif</td><td><span class="kfms-status {{ $component->is_active ? 'is-active' : 'is-muted' }}">{{ $component->is_active ? 'Active' : 'Inactive' }}</span></td><td><div class="kfms-table-actions"><a class="kfms-link-btn" href="{{ route('payroll.components.edit', $component) }}">Edit</a><form method="POST" action="{{ route('payroll.components.destroy', $component) }}">@csrf @method('DELETE')<button type="submit" onclick="return confirm('Delete this payroll component?')">Delete</button></form></div></td></tr>
            @empty
                <tr><td colspan="7" class="kfms-empty">No payroll components have been configured.</td></tr>
            @endforelse
        </tbody></table></div>
        {{ $components->links() }}
    </section>
@endsection
