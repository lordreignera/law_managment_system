@extends('layouts.admin')

@section('title', 'Audit & Maintenance')
@section('page-title', 'Audit & Maintenance')

@section('content')
    <section class="kfms-panel kfms-audit-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Audit log</h2>
                <span>Trace important changes, approvals, access actions, and system activity for maintenance and support.</span>
            </div>
            <span class="kfms-audit-readonly"><i class="mdi mdi-lock-outline"></i> Read only</span>
        </div>

        <div class="kfms-audit-stats" aria-label="Audit summary">
            <div class="kfms-audit-stat"><span>Matching records</span><strong>{{ number_format($stats['matching']) }}</strong><small>Current filters</small></div>
            <div class="kfms-audit-stat"><span>Today</span><strong>{{ number_format($stats['today']) }}</strong><small>Filtered activity</small></div>
            <div class="kfms-audit-stat"><span>Actors</span><strong>{{ number_format($stats['actors']) }}</strong><small>Staff accounts</small></div>
        </div>

        <form class="kfms-table-toolbar kfms-audit-filters" method="GET" action="{{ route('audit.index') }}">
            <label class="kfms-search-box">
                <i class="mdi mdi-magnify"></i>
                <input type="search" name="search" value="{{ $filters['search'] }}" placeholder="Search event, user, record, IP, or URL">
            </label>
            <label>
                <span>Event</span>
                <select name="event">
                    <option value="">All events</option>
                    @foreach ($events as $event)
                        <option value="{{ $event }}" @selected($filters['event'] === $event)>{{ Illuminate\Support\Str::headline(str_replace(['_', '-'], ' ', $event)) }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span>Actor</span>
                <select name="user_id">
                    <option value="">All actors</option>
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}" @selected((string) $filters['user_id'] === (string) $user->id)>{{ $user->name }} ({{ $user->email }})</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span>Record type</span>
                <select name="auditable_type">
                    <option value="">All record types</option>
                    @foreach ($auditableTypes as $type)
                        <option value="{{ $type }}" @selected($filters['auditable_type'] === $type)>{{ \App\Http\Controllers\AuditLogController::recordLabel($type) }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span>From</span>
                <input type="date" name="from" value="{{ $filters['from'] }}">
            </label>
            <label>
                <span>To</span>
                <input type="date" name="to" value="{{ $filters['to'] }}">
            </label>
            <div class="kfms-toolbar-actions">
                <button class="kfms-link-btn" type="submit"><i class="mdi mdi-filter-outline"></i> Apply</button>
                <a class="kfms-link-btn" href="{{ route('audit.index') }}">Reset</a>
            </div>
        </form>

        <div class="kfms-table-wrap">
            <table class="kfms-table kfms-audit-table">
                <thead>
                    <tr>
                        <th>When</th>
                        <th>Activity</th>
                        <th>Actor</th>
                        <th>Record</th>
                        <th>Request</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($audits as $audit)
                        @php
                            $recordType = \App\Http\Controllers\AuditLogController::recordLabel($audit->auditable_type);
                            $eventClass = match ($audit->event) {
                                'created' => 'is-created',
                                'updated' => 'is-updated',
                                'deleted' => 'is-deleted',
                                default => 'is-neutral',
                            };
                        @endphp
                        <tr>
                            <td><strong>{{ $audit->created_at?->format('d M Y') }}</strong><br><small>{{ $audit->created_at?->format('H:i:s') }}</small></td>
                            <td><span class="kfms-audit-event {{ $eventClass }}">{{ \Illuminate\Support\Str::headline(str_replace(['_', '-'], ' ', $audit->event)) }}</span></td>
                            <td>{{ $audit->user?->name ?: 'System' }}<br><small>{{ $audit->user?->email ?: 'Automated action' }}</small></td>
                            <td><strong>{{ $recordType }}</strong><br><small>{{ $audit->auditable_id ? '#'.$audit->auditable_id : 'No record reference' }}</small></td>
                            <td>{{ $audit->ip_address ?: '—' }}<br><small>{{ $audit->url ? \Illuminate\Support\Str::limit(parse_url($audit->url, PHP_URL_PATH) ?: $audit->url, 42) : 'No URL recorded' }}</small></td>
                            <td>
                                @can('audit.show')
                                    <button class="kfms-link-btn" type="button" data-bs-toggle="modal" data-bs-target="#audit-details-{{ $audit->id }}"><i class="mdi mdi-eye-outline"></i> Details</button>
                                @else
                                    <span class="kfms-muted-text">Restricted</span>
                                @endcan
                            </td>
                        </tr>
                        @can('audit.show')
                            @push('modals')
                                @include('modules.audit.partials.details-modal', ['auditLog' => $audit, 'modalId' => 'audit-details-'.$audit->id])
                            @endpush
                        @endcan
                    @empty
                        <tr><td colspan="6" class="kfms-empty">No audit activity matches these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $audits->links() }}
    </section>
@endsection
