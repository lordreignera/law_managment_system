@extends('layouts.admin')

@section('title', 'Securities')
@section('page-title', 'Securities')

@section('content')
    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <h2>Securities Registry</h2>
            <span>{{ $titles->total() }} records</span>
        </div>
        <form class="kfms-table-toolbar" method="GET" action="{{ route('land-titles.index') }}">
            <label class="kfms-search-box">
                <i class="mdi mdi-magnify"></i>
                <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search reference, borrower, or instruction">
            </label>
            <label>
                <span>Status</span>
                <select name="status">
                    <option value="">All Statuses</option>
                    <option value="pending" @selected(($filters['status'] ?? '') === 'pending')>Pending</option>
                    <option value="received" @selected(($filters['status'] ?? '') === 'received')>Received</option>
                    <option value="in_progress" @selected(($filters['status'] ?? '') === 'in_progress')>In Progress</option>
                    <option value="returned" @selected(($filters['status'] ?? '') === 'returned')>Returned</option>
                </select>
            </label>
            <div class="kfms-toolbar-actions">
                <button class="kfms-link-btn" type="submit">Apply Filters</button>
                <a class="kfms-link-btn" href="{{ route('land-titles.index') }}">Reset</a>
            </div>
        </form>
        @include('modules.partials.table', [
            'headers' => ['Reference', 'Borrower', 'Instruction', 'Received', 'Returned', 'Status'],
            'rows' => $titles->map(fn ($title) => [$title->reference_no, $title->borrower_name, $title->instruction_type, $title->received_on?->format('d M Y'), $title->returned_on?->format('d M Y'), $title->status]),
        ])
        {{ $titles->links() }}
    </section>
@endsection
