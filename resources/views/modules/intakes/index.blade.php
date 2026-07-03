@extends('layouts.admin')

@section('title', 'Client Intakes')
@section('page-title', 'Client Intakes')

@section('content')
    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Intake Register</h2>
                <span>{{ $intakes->total() }} intake requests</span>
            </div>
            <a class="kfms-btn" href="{{ route('intakes.create') }}">
                <i class="mdi mdi-plus"></i>
                New Intake
            </a>
        </div>

        @if (session('status'))
            <div class="kfms-alert">{{ session('status') }}</div>
        @endif

        <form class="kfms-table-toolbar" method="GET" action="{{ route('intakes.index') }}">
            <label class="kfms-search-box">
                <i class="mdi mdi-magnify"></i>
                <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search intake, client, or issue">
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
                <span>Decision</span>
                <select name="review_decision">
                    <option value="">All Decisions</option>
                    @foreach ($reviewDecisions as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['review_decision'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <div class="kfms-toolbar-actions">
                <button class="kfms-link-btn" type="submit">Apply Filters</button>
                <a class="kfms-link-btn" href="{{ route('intakes.index') }}">Reset</a>
            </div>
        </form>

        <div class="kfms-table-wrap">
            <table class="kfms-table">
                <thead>
                    <tr>
                        <th>Intake</th>
                        <th>Client</th>
                        <th>Legal Issue</th>
                        <th>Practice Area</th>
                        <th>Status</th>
                        <th>Decision</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($intakes as $intake)
                        <tr>
                            <td>{{ $intake->intake_no }}</td>
                            <td>{{ $intake->client_name }}</td>
                            <td>{{ $intake->legal_issue }}</td>
                            <td>{{ $intake->practiceArea?->name ?: '-' }}</td>
                            <td>{{ $intake->statusLabel() }}</td>
                            <td>{{ $intake->reviewDecisionLabel() }}</td>
                            <td>
                                <a class="kfms-link-btn" href="{{ route('intakes.show', $intake) }}">Review / Decide</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="kfms-empty">No intake requests yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $intakes->links() }}
    </section>
@endsection
