@extends('layouts.admin')

@section('title', 'Litigation Dashboard')
@section('page-title', 'Litigation Dashboard')

@section('content')
    <div class="kfms-stat-grid">
        @foreach ($stats as $label => $value)
            <section class="kfms-card">
                <span class="kfms-card-label">{{ $label }}</span>
                <strong class="kfms-stat">{{ number_format($value) }}</strong>
            </section>
        @endforeach
    </div>

    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Litigation Lifecycle</h2>
                <span>From retained-client instructions to judgment, taxation, execution, and closure</span>
            </div>
            <div class="kfms-toolbar-actions">
                @can('litigation.create')
                    <a class="kfms-btn" href="{{ route('litigation.create') }}">
                        <i class="mdi mdi-calendar-plus"></i>
                        Schedule Event
                    </a>
                @endcan
            </div>
        </div>

        <div class="kfms-litigation-actions">
            @foreach ($lifecycle as $item)
                <article class="kfms-litigation-step kfms-litigation-step-{{ $item['tone'] ?? 'navy' }}">
                    <span class="kfms-litigation-step-icon">
                        <i class="mdi {{ $item['icon'] ?? 'mdi-briefcase-outline' }}"></i>
                    </span>
                    <div class="kfms-litigation-step-main">
                        <strong>{{ $item['stage'] }}</strong>
                        <p>{{ $item['description'] }}</p>
                    </div>
                    <em>{{ number_format($item['count']) }}</em>
                    <div class="kfms-litigation-step-actions">
                        <a href="{{ $item['route'] }}">
                            <i class="mdi mdi-arrow-right"></i>
                            {{ $item['action'] ?? 'Open' }}
                        </a>
                        @isset($item['eventRoute'])
                            @can('litigation.create')
                                <a href="{{ $item['eventRoute'] }}">
                                    <i class="mdi mdi-plus"></i>
                                    {{ $item['eventAction'] ?? 'Add Event' }}
                                </a>
                            @endcan
                        @endisset
                        @isset($item['exportRoute'])
                            @can('litigation.export')
                                <a href="{{ $item['exportRoute'] }}">
                                    <i class="mdi mdi-microsoft-excel"></i>
                                    Export
                                </a>
                            @endcan
                        @endisset
                    </div>
                </article>
            @endforeach
        </div>
    </section>

    <div class="kfms-grid-two">
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
