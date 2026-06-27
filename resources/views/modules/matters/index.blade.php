@extends('layouts.admin')

@section('title', 'Matters')
@section('page-title', 'Matters')

@section('content')
    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Matter Register</h2>
                <span>{{ $matters->total() }} records</span>
            </div>
            @can('manage intakes')
                <a class="kfms-btn" href="{{ route('intakes.create') }}">
                    <i class="mdi mdi-plus"></i>
                    Start Client Intake
                </a>
            @endcan
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
                            <td><a class="kfms-link-btn" href="{{ route('matters.show', $matter) }}">Review</a></td>
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
