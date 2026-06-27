@extends('layouts.admin')

@section('title', 'Requisitions')
@section('page-title', 'Requisitions')

@section('content')
    <div class="kfms-stat-grid">
        @foreach ($summary as $label => $value)
            <section class="kfms-card">
                <span class="kfms-card-label">{{ $label }}</span>
                <strong class="kfms-stat">{{ $label === 'Approved Value' ? number_format($value, 2) : number_format($value) }}</strong>
            </section>
        @endforeach
    </div>

    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>{{ $canApprove ? 'Requisitions (Finance Review)' : 'My Requisitions' }}</h2>
                <span>{{ $requisitions->total() }} records</span>
            </div>
            <a class="kfms-btn" href="{{ route('requisitions.create') }}">
                <i class="mdi mdi-plus"></i>
                New Requisition
            </a>
        </div>

        @if (session('status'))
            <div class="kfms-alert">{{ session('status') }}</div>
        @endif

        <form class="kfms-table-toolbar" method="GET" action="{{ route('requisitions.index') }}">
            <label class="kfms-search-box">
                <i class="mdi mdi-magnify"></i>
                <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search reference, purpose or staff">
            </label>
            <label>
                <span>Status</span>
                <select name="status">
                    <option value="">All Statuses</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span>Category</span>
                <select name="requisition_category_id">
                    <option value="">All Categories</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) ($filters['requisition_category_id'] ?? '') === (string) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </label>
            <div class="kfms-toolbar-actions">
                <button class="kfms-link-btn" type="submit">Apply Filters</button>
                <a class="kfms-link-btn" href="{{ route('requisitions.index') }}">Reset</a>
            </div>
        </form>

        <div class="kfms-table-wrap">
            <table class="kfms-table">
                <thead>
                    <tr>
                        <th>Reference</th>
                        @if ($canApprove)<th>Requested By</th>@endif
                        <th>Category</th>
                        <th>Purpose</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($requisitions as $requisition)
                        <tr>
                            <td>{{ $requisition->reference_no }}</td>
                            @if ($canApprove)<td>{{ $requisition->requester?->name }}</td>@endif
                            <td>{{ $requisition->category?->name ?: '-' }}</td>
                            <td>{{ \Illuminate\Support\Str::limit($requisition->purpose, 40) }}</td>
                            <td>{{ number_format($requisition->amount, 2) }}</td>
                            <td><span class="kfms-status kfms-status-{{ $requisition->status }}">{{ $requisition->statusLabel() }}</span></td>
                            <td><a class="kfms-link-btn" href="{{ route('requisitions.show', $requisition) }}">View</a></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $canApprove ? 7 : 6 }}" class="kfms-empty">No requisitions yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $requisitions->links() }}
    </section>
@endsection
