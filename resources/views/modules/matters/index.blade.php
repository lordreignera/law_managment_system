@extends('layouts.admin')

@section('title', 'Matters')
@section('page-title', 'Matters')

@section('content')
    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Matter Register</h2>
                <span>{{ $matters->total() }} internal firm files</span>
            </div>
            <div class="kfms-toolbar-actions">
                @can('matters.export')
                    <a class="kfms-link-btn" href="{{ route('matters.export') }}">
                        <i class="mdi mdi-microsoft-excel"></i>
                        Export
                    </a>
                @endcan
                @can('matters.import')
                    <form class="kfms-inline-upload" method="POST" action="{{ route('matters.import') }}" enctype="multipart/form-data">
                        @csrf
                        <input type="file" name="file" accept=".xlsx,.xls,.csv" required>
                        <button type="submit"><i class="mdi mdi-upload"></i> Import</button>
                    </form>
                @endcan
                @can('intakes.create')
                    <a class="kfms-link-btn" href="{{ route('intakes.create') }}">
                        <i class="mdi mdi-account-plus-outline"></i>
                        Walk-in Intake
                    </a>
                @endcan
            </div>
        </div>

        @if (session('status'))
            <div class="kfms-alert">{{ session('status') }}</div>
        @endif
        <form class="kfms-table-toolbar" method="GET" action="{{ route('matters.index') }}">
            <label class="kfms-search-box">
                <i class="mdi mdi-magnify"></i>
                <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search reference, title, or client">
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
                <a class="kfms-link-btn" href="{{ route('matters.index') }}">Reset</a>
            </div>
        </form>
        <div class="kfms-table-wrap">
            <table class="kfms-table">
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Title</th>
                        <th>Client</th>
                        <th>Practice Area</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($matters as $matter)
                        <tr>
                            <td>{{ $matter->reference_no }}</td>
                            <td>{{ $matter->title }}</td>
                            <td>{{ $matter->client?->name ?: '-' }}</td>
                            <td>{{ $matter->practiceArea?->name ?: '-' }}</td>
                            <td>{{ $matter->statusLabel() }}</td>
                            <td><a class="kfms-link-btn" href="{{ route('matters.show', $matter) }}">Open Workspace</a></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="kfms-empty">No matters found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $matters->links() }}
    </section>
@endsection
