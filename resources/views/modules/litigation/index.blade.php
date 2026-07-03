@extends('layouts.admin')

@section('title', 'Cause List')
@section('page-title', 'Litigation — Cause List')

@section('content')
    <div class="kfms-stat-grid">
        @foreach ($summary as $label => $value)
            <section class="kfms-card">
                <span class="kfms-card-label">{{ $label }}</span>
                <strong class="kfms-stat">{{ number_format($value) }}</strong>
            </section>
        @endforeach
    </div>

    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Cause List</h2>
                <span>{{ $events->total() }} court events</span>
            </div>
            <div class="kfms-toolbar-actions">
                @can('litigation.export')
                    <a class="kfms-link-btn" href="{{ route('litigation.export') }}">
                        <i class="mdi mdi-microsoft-excel"></i>
                        Export
                    </a>
                @endcan
                @can('litigation.import')
                    <form class="kfms-inline-upload" method="POST" action="{{ route('litigation.import') }}" enctype="multipart/form-data">
                        @csrf
                        <input type="file" name="file" accept=".xlsx,.xls,.csv" required>
                        <button type="submit"><i class="mdi mdi-upload"></i> Import</button>
                    </form>
                @endcan
                <a class="kfms-link-btn" href="{{ route('calendar.index') }}">
                    <i class="mdi mdi-calendar-month"></i>
                    Calendar
                </a>
                <a class="kfms-btn" href="{{ route('litigation.create') }}">
                    <i class="mdi mdi-plus"></i>
                    Schedule Event
                </a>
            </div>
        </div>

        @if (session('status'))
            <div class="kfms-alert">{{ session('status') }}</div>
        @endif

        <form class="kfms-table-toolbar" method="GET" action="{{ route('litigation.index') }}">
            <label class="kfms-search-box">
                <i class="mdi mdi-magnify"></i>
                <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search case no, court, officer or matter">
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
                <span>Event Type</span>
                <select name="event_type">
                    <option value="">All Types</option>
                    @foreach ($eventTypes as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['event_type'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span>Advocate</span>
                <select name="assigned_to">
                    <option value="">All</option>
                    @foreach ($officers as $officer)
                        <option value="{{ $officer->id }}" @selected((string) ($filters['assigned_to'] ?? '') === (string) $officer->id)>{{ $officer->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="kfms-checkbox">
                <input type="checkbox" name="mine" value="1" @checked(! empty($filters['mine']))>
                <span>Assigned to me</span>
            </label>
            <div class="kfms-toolbar-actions">
                <button class="kfms-link-btn" type="submit">Apply Filters</button>
                <a class="kfms-link-btn" href="{{ route('litigation.index') }}">Reset</a>
            </div>
        </form>

        <div class="kfms-table-wrap">
            <table class="kfms-table">
                <thead>
                    <tr>
                        <th>Date &amp; Time</th>
                        <th>Matter</th>
                        <th>Case No.</th>
                        <th>Court</th>
                        <th>Type</th>
                        <th>Advocate</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($events as $event)
                        <tr>
                            <td>
                                {{ $event->starts_at?->format('d M Y, H:i') }}
                                @if ($event->isOverdue())
                                    <span class="kfms-status kfms-status-rejected">Overdue</span>
                                @endif
                            </td>
                            <td>
                                <strong>{{ $event->matter?->reference_no }}</strong><br>
                                <span class="kfms-muted">{{ \Illuminate\Support\Str::limit($event->matter?->title, 32) }}</span>
                            </td>
                            <td>{{ $event->case_number ?: '-' }}</td>
                            <td>{{ $event->court?->name ?: $event->court_name ?: '-' }}</td>
                            <td>{{ $event->eventTypeLabel() }}</td>
                            <td>{{ $event->assignee?->name ?: '-' }}</td>
                            <td><span class="kfms-status kfms-status-{{ $event->status }}">{{ $event->statusLabel() }}</span></td>
                            <td><a class="kfms-link-btn" href="{{ route('litigation.show', $event) }}">View</a></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="kfms-empty">No court events scheduled.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $events->links() }}
    </section>
@endsection
