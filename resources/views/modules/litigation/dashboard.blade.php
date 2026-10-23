@extends('layouts.admin')

@section('title', 'Litigation Dashboard')
@section('page-title', 'Litigation Dashboard')

@section('content')
    <section class="kfms-dashboard-hero kfms-litigation-dashboard-hero">
        <div>
            <span>Litigation control room</span>
            <h2>Track court work, deadlines, outcomes, and next steps.</h2>
            <p>Advocates monitor cause lists, active court events, overdue dates, lifecycle stages, rulings, taxation, and execution follow-up.</p>
        </div>
        <div class="kfms-dashboard-hero-actions">
            @can('litigation.create')
                <a class="kfms-btn" href="{{ route('litigation.create') }}">
                    <i class="mdi mdi-calendar-plus"></i>
                    Schedule Court Event
                </a>
            @endcan
            @can('litigation.index')
                <a class="kfms-link-btn" href="{{ route('litigation.index') }}">
                    <i class="mdi mdi-format-list-bulleted"></i>
                    Cause List
                </a>
            @endcan
            @can('litigation.export')
                <a class="kfms-link-btn" href="{{ route('litigation.export') }}">
                    <i class="mdi mdi-download"></i>
                    Export
                </a>
            @endcan
        </div>
    </section>

    <div class="kfms-stat-grid kfms-dashboard-kpis">
        @foreach ($stats as $label => $value)
            <section class="kfms-card kfms-stat-card">
                <span class="kfms-stat-body">
                    <span class="kfms-card-label">{{ $label }}</span>
                    <strong class="kfms-stat">{{ number_format($value) }}</strong>
                </span>
            </section>
        @endforeach
    </div>

    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Litigation Lifecycle</h2>
                <span>From opened litigation matters to judgment, taxation, execution, and closure</span>
            </div>
        </div>

        <div class="kfms-litigation-actions" role="group" aria-label="Litigation lifecycle stages">
            @foreach ($lifecycle as $index => $item)
                @php($descriptionId = 'litigation-stage-description-' . $index)
                <div class="kfms-litigation-stage-group kfms-litigation-step-{{ $item['tone'] ?? 'navy' }}">
                    <a class="kfms-litigation-stage-button"
                       href="{{ $item['route'] }}"
                       title="{{ $item['action'] ?? 'Open' }}. {{ $item['description'] }}"
                       aria-label="{{ $item['stage'] }}: {{ number_format($item['count']) }}. {{ $item['description'] }}"
                       aria-describedby="{{ $descriptionId }}"
                       data-tooltip="{{ $item['description'] }}">
                        <i class="mdi {{ $item['icon'] ?? 'mdi-briefcase-outline' }}" aria-hidden="true"></i>
                        <span>{{ $item['stage'] }}</span>
                        <em>{{ number_format($item['count']) }}</em>
                    </a>
                    <span class="kfms-visually-hidden" id="{{ $descriptionId }}">{{ $item['description'] }}</span>

                    @isset($item['eventRoute'])
                        @can('litigation.create')
                            <a class="kfms-litigation-stage-quick-action"
                               href="{{ $item['eventRoute'] }}"
                               title="{{ $item['eventAction'] ?? 'Add Event' }}"
                               aria-label="{{ $item['eventAction'] ?? 'Add Event' }}"
                               data-tooltip="{{ $item['eventAction'] ?? 'Add Event' }}">
                                <i class="mdi mdi-plus" aria-hidden="true"></i>
                            </a>
                        @endcan
                    @endisset
                    @isset($item['exportRoute'])
                        @can('litigation.export')
                            <a class="kfms-litigation-stage-quick-action"
                               href="{{ $item['exportRoute'] }}"
                               title="Export {{ $item['stage'] }}"
                               aria-label="Export {{ $item['stage'] }}"
                               data-tooltip="Export {{ $item['stage'] }}">
                                <i class="mdi mdi-microsoft-excel" aria-hidden="true"></i>
                            </a>
                        @endcan
                    @endisset
                </div>
            @endforeach
        </div>
    </section>

    <div class="kfms-grid-two kfms-litigation-dashboard-grid">
        <section class="kfms-panel">
            <div class="kfms-panel-header">
                <div>
                    <h2>My Court Work</h2>
                    <span>Open events assigned to you</span>
                </div>
                <a class="kfms-link-btn" href="{{ route('litigation.index', ['mine' => 1]) }}">View Mine</a>
            </div>

            <div class="kfms-table-wrap">
                <table class="kfms-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Matter</th>
                            <th>Type</th>
                            <th>Court</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($myEvents as $event)
                            <tr>
                                <td>{{ $event->starts_at?->format('d M, H:i') }}</td>
                                <td>
                                    <a href="{{ route('litigation.show', $event) }}">{{ $event->matter?->reference_no ?: 'Court Event' }}</a><br>
                                    <span class="kfms-muted">{{ \Illuminate\Support\Str::limit($event->matter?->title, 34) }}</span>
                                </td>
                                <td>{{ $event->eventTypeLabel() }}</td>
                                <td>{{ $event->court?->name ?: $event->court_name ?: '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="kfms-empty">No open court events assigned to you.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="kfms-panel">
            <div class="kfms-panel-header">
                <div>
                    <h2>Next Steps</h2>
                    <span>Deadlines from outcomes and court directions</span>
                </div>
                <a class="kfms-link-btn" href="{{ route('litigation.index') }}">Cause List</a>
            </div>

            <div class="kfms-action-list">
                @forelse ($nextSteps as $event)
                    <a href="{{ route('litigation.show', $event) }}">
                        <strong>{{ $event->next_step }}</strong>
                        <span>
                            {{ $event->next_step_due?->format('d M Y') }}
                            @if ($event->assignee)
                                / {{ $event->assignee->name }}
                            @endif
                        </span>
                    </a>
                @empty
                    <div class="kfms-empty">No pending next steps captured.</div>
                @endforelse
            </div>
        </section>
    </div>

    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Upcoming Cause List</h2>
                <span>Scheduled and adjourned matters across the litigation team</span>
            </div>
        </div>

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
                    </tr>
                </thead>
                <tbody>
                    @forelse ($upcomingEvents as $event)
                        <tr>
                            <td>{{ $event->starts_at?->format('d M Y, H:i') }}</td>
                            <td>
                                <a href="{{ route('litigation.show', $event) }}">{{ $event->matter?->reference_no ?: '-' }}</a><br>
                                <span class="kfms-muted">{{ \Illuminate\Support\Str::limit($event->matter?->title, 42) }}</span>
                            </td>
                            <td>{{ $event->case_number ?: '-' }}</td>
                            <td>{{ $event->court?->name ?: $event->court_name ?: '-' }}</td>
                            <td>{{ $event->eventTypeLabel() }}</td>
                            <td>{{ $event->assignee?->name ?: '-' }}</td>
                            <td><span class="kfms-status kfms-status-{{ $event->status }}">{{ $event->statusLabel() }}</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="kfms-empty">No upcoming court events.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
