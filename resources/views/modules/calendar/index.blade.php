@extends('layouts.admin')

@section('title', 'Firm Calendar')
@section('page-title', 'Firm Calendar')

@section('content')
    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>{{ $month->format('F Y') }}</h2>
                <span>Meetings, court dates &amp; public holidays</span>
            </div>
            <div class="kfms-toolbar-actions">
                <a class="kfms-link-btn" href="{{ route('calendar.index', ['month' => $month->copy()->subMonth()->format('Y-m')]) }}">
                    <i class="mdi mdi-chevron-left"></i>
                    Prev
                </a>
                <a class="kfms-link-btn" href="{{ route('calendar.index') }}">Today</a>
                <a class="kfms-link-btn" href="{{ route('calendar.index', ['month' => $month->copy()->addMonth()->format('Y-m')]) }}">
                    Next
                    <i class="mdi mdi-chevron-right"></i>
                </a>
                <form class="kfms-calendar-jump" method="GET" action="{{ route('calendar.index') }}">
                    <select name="m" aria-label="Month">
                        @for ($m = 1; $m <= 12; $m++)
                            <option value="{{ $m }}" @selected((int) $month->month === $m)>{{ \Illuminate\Support\Carbon::create(null, $m)->format('F') }}</option>
                        @endfor
                    </select>
                    <select name="y" aria-label="Year">
                        @for ($y = now()->year - 5; $y <= now()->year + 5; $y++)
                            <option value="{{ $y }}" @selected((int) $month->year === $y)>{{ $y }}</option>
                        @endfor
                    </select>
                    <button class="kfms-link-btn" type="submit">Go</button>
                </form>
                <a class="kfms-btn" href="{{ route('calendar.create') }}">
                    <i class="mdi mdi-plus"></i>
                    Schedule
                </a>
            </div>
        </div>

        @if (session('status'))
            <div class="kfms-alert">{{ session('status') }}</div>
        @endif

        <div class="kfms-calendar">
            <div class="kfms-calendar-head">
                @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $weekday)
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
                    <div class="kfms-calendar-cell
                        @if ($day->month !== $month->month) is-muted @endif
                        @if ($day->isToday()) is-today @endif
                        @if ($day->isWeekend()) is-weekend @endif
                        @if ($holidayName) is-holiday @endif">
                        <a class="kfms-calendar-date" href="{{ route('calendar.create', ['date' => $dayKey]) }}" title="Schedule on {{ $day->format('d M Y') }}">{{ $day->day }}</a>
                        @if ($holidayName)
                            <span class="kfms-calendar-holiday" title="{{ $holidayName }}">{{ \Illuminate\Support\Str::limit($holidayName, 18) }}</span>
                        @endif
                        @foreach ($dayEvents as $event)
                            <a class="kfms-calendar-event kfms-event-{{ $event['kind'] }} kfms-status-{{ $event['status'] }}" href="{{ $event['url'] }}" title="{{ $event['label'] }}">
                                @if ($event['time']){{ $event['time'] }} @endif{{ \Illuminate\Support\Str::limit($event['label'], 16) }}
                            </a>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>

        <div class="kfms-calendar-legend">
            <span><i class="kfms-dot kfms-event-calendar"></i> Meeting / Event</span>
            <span><i class="kfms-dot kfms-event-court"></i> Court Date</span>
            <span><i class="kfms-dot kfms-dot-holiday"></i> Public Holiday</span>
            <span><i class="kfms-dot kfms-dot-weekend"></i> Weekend</span>
        </div>
    </section>
@endsection
