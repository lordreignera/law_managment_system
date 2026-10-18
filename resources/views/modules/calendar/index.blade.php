@extends('layouts.admin')

@section('title', 'Firm Calendar')
@section('page-title', 'Firm Calendar')

@php
    $calendarScope = $personalOnly ? ['scope' => 'mine'] : [];
    $miniStart = $month->copy()->startOfMonth()->startOfWeek(0);
    $miniEnd = $month->copy()->endOfMonth()->endOfWeek(6);
    $miniDays = [];
    for ($cursor = $miniStart->copy(); $cursor->lte($miniEnd); $cursor->addDay()) {
        $miniDays[] = $cursor->copy();
    }
    $weekdays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
@endphp

@section('content')
    <section class="kfms-calendar-page">
        <header class="kfms-calendar-page-header">
            <div>
                <span class="kfms-calendar-eyebrow">Calendar overview</span>
                <h1>Firm Calendar</h1>
                <p>Keep meetings, court dates, deadlines and public holidays in one shared view.</p>
            </div>
            <div class="kfms-calendar-page-actions">
                <a class="kfms-calendar-icon-btn" href="{{ route('calendar.index', $calendarScope + ['month' => $month->copy()->subMonth()->format('Y-m')]) }}" aria-label="Previous month" title="Previous month">
                    <i class="mdi mdi-chevron-left"></i>
                </a>
                <a class="kfms-calendar-today-btn" href="{{ route('calendar.index', $calendarScope) }}">Today</a>
                <a class="kfms-calendar-icon-btn" href="{{ route('calendar.index', $calendarScope + ['month' => $month->copy()->addMonth()->format('Y-m')]) }}" aria-label="Next month" title="Next month">
                    <i class="mdi mdi-chevron-right"></i>
                </a>
                <a class="kfms-btn kfms-calendar-create-trigger" href="{{ route('calendar.create') }}" data-bs-toggle="modal" data-bs-target="#calendar-create-modal" data-calendar-create-date="{{ now()->toDateString() }}">
                    <i class="mdi mdi-plus"></i>
                    Schedule event
                </a>
            </div>
        </header>

        <div class="kfms-calendar-toolbar">
            <div class="kfms-calendar-scope" role="group" aria-label="Calendar scope">
                <a href="{{ route('calendar.index', ['month' => $month->format('Y-m')]) }}" @class(['is-active' => ! $personalOnly])>
                    <i class="mdi mdi-calendar-multiple"></i> All calendars
                </a>
                <a href="{{ route('calendar.index', ['month' => $month->format('Y-m'), 'scope' => 'mine']) }}" @class(['is-active' => $personalOnly])>
                    <i class="mdi mdi-account-calendar-outline"></i> My calendar
                </a>
            </div>
            <form class="kfms-calendar-jump kfms-calendar-toolbar-jump" method="GET" action="{{ route('calendar.index') }}">
                @if ($personalOnly)<input type="hidden" name="scope" value="mine">@endif
                <label>
                    <span>Month</span>
                    <select name="m" aria-label="Month">
                        @for ($m = 1; $m <= 12; $m++)
                            <option value="{{ $m }}" @selected((int) $month->month === $m)>{{ \Carbon\Carbon::create(2000, $m, 1)->format('F') }}</option>
                        @endfor
                    </select>
                </label>
                <label>
                    <span>Year</span>
                    <select name="y" aria-label="Year">
                        @for ($y = now()->year - 5; $y <= now()->year + 5; $y++)
                            <option value="{{ $y }}" @selected((int) $month->year === $y)>{{ $y }}</option>
                        @endfor
                    </select>
                </label>
                <button class="kfms-link-btn" type="submit">Apply</button>
            </form>
        </div>

        @if (session('status'))
            <div class="kfms-alert">{{ session('status') }}</div>
        @endif

        <div class="kfms-calendar-workspace">
            <aside class="kfms-calendar-sidebar">
                <section class="kfms-calendar-mini-card">
                    <div class="kfms-calendar-mini-heading">
                        <strong>{{ $month->format('F Y') }}</strong>
                        <span>{{ $month->year }}</span>
                    </div>
                    <div class="kfms-calendar-mini-weekdays">
                        @foreach ($weekdays as $weekday)
                            <span>{{ substr($weekday, 0, 1) }}</span>
                        @endforeach
                    </div>
                    <div class="kfms-calendar-mini-grid">
                        @foreach ($miniDays as $miniDay)
                            @php
                                $miniKey = $miniDay->toDateString();
                                $miniHasEvents = isset($events[$miniKey]) && $events[$miniKey]->isNotEmpty();
                            @endphp
                            <a href="{{ route('calendar.index', $calendarScope + ['month' => $miniDay->format('Y-m')]) }}" @class([
                                'is-muted' => $miniDay->month !== $month->month,
                                'is-today' => $miniDay->isToday(),
                                'has-events' => $miniHasEvents,
                            ])>{{ $miniDay->day }}</a>
                        @endforeach
                    </div>
                </section>

                <section class="kfms-calendar-upcoming-card">
                    <div class="kfms-calendar-side-heading">
                        <h2>Upcoming events</h2>
                        <span>{{ $month->format('M') }}</span>
                    </div>
                    <div class="kfms-calendar-upcoming-list">
                        @forelse ($upcomingEvents as $upcoming)
                            <a href="{{ $upcoming['url'] }}">
                                <span class="kfms-calendar-upcoming-date">{{ \Illuminate\Support\Carbon::parse($upcoming['date'])->format('d M') }}</span>
                                <strong>{{ $upcoming['label'] }}</strong>
                                <small>{{ $upcoming['time'] ?: 'All day' }}</small>
                            </a>
                        @empty
                            <div class="kfms-calendar-empty-upcoming">No events in {{ $month->format('F') }}.</div>
                        @endforelse
                    </div>
                </section>

                <section class="kfms-calendar-tip-card">
                    <div>
                        <strong>Stay on top of schedules</strong>
                        <p>Schedule internal events and deadlines without missing important dates.</p>
                    </div>
                    <i class="mdi mdi-calendar-check-outline"></i>
                </section>
            </aside>

            <section class="kfms-calendar-main">
                <div class="kfms-calendar-main-heading">
                    <div>
                        <h2>{{ $month->format('F Y') }} Calendar</h2>
                        <span>{{ $personalOnly ? 'Your assigned events and visible court dates.' : 'All calendars visible to you. Sundays and holidays are marked.' }}</span>
                    </div>
                    <div class="kfms-calendar-view-switch" role="group" aria-label="Calendar view">
                        <span class="is-active">Month</span>
                        <span>Week</span>
                        <span>Day</span>
                        <span>List</span>
                    </div>
                </div>

                <div class="kfms-calendar">
                    <div class="kfms-calendar-head">
                        @foreach ($weekdays as $weekday)
                            <span>{{ $weekday }}</span>
                        @endforeach
                    </div>
                    <div class="kfms-calendar-grid">
                        @foreach ($days as $day)
                            @php
                                $dayKey = $day->toDateString();
                                $dayEvents = $events[$dayKey] ?? collect();
                                $holidayName = $holidays[$dayKey] ?? null;
                            @endphp
                            <div @class([
                                'kfms-calendar-cell',
                                'is-muted' => $day->month !== $month->month,
                                'is-today' => $day->isToday(),
                                'is-weekend' => $day->isWeekend(),
                                'is-holiday' => $holidayName,
                            ])>
                                <a class="kfms-calendar-date" href="{{ route('calendar.create', ['date' => $dayKey]) }}" data-bs-toggle="modal" data-bs-target="#calendar-create-modal" data-calendar-create-date="{{ $dayKey }}" title="Schedule on {{ $day->format('d M Y') }}">
                                    <span>{{ $day->day }}</span>
                                    @if ($day->isToday())<em>Today</em>@endif
                                </a>
                                @if ($holidayName)
                                    <span class="kfms-calendar-holiday" title="{{ $holidayName }}">{{ \Illuminate\Support\Str::limit($holidayName, 18) }}</span>
                                @endif
                                @foreach ($dayEvents as $calendarEvent)
                                    <a class="kfms-calendar-event kfms-event-{{ $calendarEvent['kind'] }} kfms-status-{{ $calendarEvent['status'] }}" href="{{ $calendarEvent['url'] }}" title="{{ $calendarEvent['label'] }}">
                                        @if ($calendarEvent['time'])<time>{{ $calendarEvent['time'] }}</time>@endif
                                        <span>{{ \Illuminate\Support\Str::limit($calendarEvent['label'], 24) }}</span>
                                    </a>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="kfms-calendar-mobile-agenda">
                    <h3>Month agenda</h3>
                    @forelse ($upcomingEvents as $upcoming)
                        <a href="{{ $upcoming['url'] }}">
                            <span>{{ \Illuminate\Support\Carbon::parse($upcoming['date'])->format('d M Y') }}</span>
                            <strong>{{ $upcoming['label'] }}</strong>
                            <small>{{ $upcoming['time'] ?: 'All day' }}</small>
                        </a>
                    @empty
                        <p>No events scheduled for this month.</p>
                    @endforelse
                </div>

                <div class="kfms-calendar-legend">
                    <span><i class="kfms-dot kfms-event-calendar"></i> Meeting / Event</span>
                    <span><i class="kfms-dot kfms-event-court"></i> Court Date</span>
                    <span><i class="kfms-dot kfms-dot-holiday"></i> Public Holiday</span>
                    <span><i class="kfms-dot kfms-dot-weekend"></i> Weekend</span>
                </div>
            </section>
        </div>
    </section>
@endsection

@push('modals')
    <div class="modal fade kfms-modal kfms-calendar-create-modal" id="calendar-create-modal" tabindex="-1" aria-labelledby="calendar-create-modal-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <form class="kfms-form" method="POST" action="{{ route('calendar.store') }}" id="calendar-create-form">
                    @csrf
                    <div class="modal-header">
                        <div>
                            <h2 class="modal-title" id="calendar-create-modal-title">Schedule event</h2>
                            <span>Add an event without leaving your calendar.</span>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        @include('modules.calendar.partials.form')
                    </div>
                    <div class="kfms-form-actions modal-footer">
                        <button class="kfms-link-btn" type="button" data-bs-dismiss="modal">Cancel</button>
                        <button class="kfms-btn" type="submit">
                            <i class="mdi mdi-calendar-plus"></i>
                            Schedule event
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endpush

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const modalElement = document.getElementById('calendar-create-modal');
            const form = document.getElementById('calendar-create-form');
            const startsAt = form?.querySelector('[name="starts_at"]');

            document.querySelectorAll('[data-calendar-create-date]').forEach((trigger) => {
                trigger.addEventListener('click', () => {
                    const date = trigger.dataset.calendarCreateDate;
                    if (startsAt && date) startsAt.value = `${date}T09:00`;
                });
            });

            @if ($errors->any())
                if (modalElement && window.bootstrap?.Modal) {
                    window.bootstrap.Modal.getOrCreateInstance(modalElement).show();
                }
            @endif
        });
    </script>
@endpush
