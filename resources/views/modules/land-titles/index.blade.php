@extends('layouts.admin')

@section('title', 'Securities')
@section('page-title', 'Securities')

@section('content')
    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Securities Registry</h2>
                <span>{{ $titles->total() }} records</span>
            </div>
            <div class="kfms-row-actions">
                <a class="kfms-link-btn" href="{{ route('land-titles.export', $filters) }}">
                    <i class="mdi mdi-download"></i>
                    Export
                </a>
                <a class="kfms-btn" href="{{ route('land-titles.create') }}">
                    <i class="mdi mdi-plus"></i>
                    Add Security
                </a>
            </div>
        </div>

        @if (session('status'))
            <div class="kfms-alert">{{ session('status') }}</div>
        @endif

        <form class="kfms-table-toolbar" method="GET" action="{{ route('land-titles.index') }}">
            <label class="kfms-search-box">
                <i class="mdi mdi-magnify"></i>
                <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search reference, borrower, or instruction">
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
            <div class="kfms-toolbar-actions">
                <button class="kfms-link-btn" type="submit">Apply Filters</button>
                <a class="kfms-link-btn" href="{{ route('land-titles.index') }}">Reset</a>
            </div>
        </form>

        <div class="kfms-table-wrap">
            <table class="kfms-table">
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Borrower</th>
                        <th>Institution / Branch</th>
                        <th>MZO</th>
                        <th>Instruction</th>
                        <th>Received</th>
                        <th>Returned</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($titles as $title)
                        <tr>
                            <td>{{ $title->reference_no }}</td>
                            <td>{{ $title->borrower_name }}</td>
                            <td>
                                {{ $title->bank?->name ?: '-' }}<br>
                                <span class="kfms-muted">{{ $title->bankBranch?->name ?: $title->received_from ?: '-' }}</span>
                            </td>
                            <td>{{ $title->zonalOffice?->name ?: '-' }}</td>
                            <td>{{ $title->instruction_type ?: '-' }}</td>
                            <td>{{ $title->received_at?->format('d M Y, H:i') ?: '-' }}</td>
                            <td>{{ $title->returned_at?->format('d M Y, H:i') ?: '-' }}</td>
                            <td>{{ $title->statusLabel() }}</td>
                            <td>
                                <div class="kfms-table-actions">
                                    <a href="{{ route('land-titles.show', $title) }}">View</a>
                                    <a href="{{ route('land-titles.edit', $title) }}">Edit</a>
                                    <form method="POST" action="{{ route('land-titles.destroy', $title) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button class="is-danger" type="submit" onclick="return confirm('Delete this security?')">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="kfms-empty">No securities found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $titles->links() }}
    </section>
@endsection
