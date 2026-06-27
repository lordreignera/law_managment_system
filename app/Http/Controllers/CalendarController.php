<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\CalendarEvent;
use App\Models\CourtEvent;
use App\Models\Matter;
use App\Models\PublicHoliday;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CalendarController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $month = $this->resolveMonth($request);
        $rangeStart = $month->copy()->startOfMonth();
        $rangeEnd = $month->copy()->endOfMonth();

        // General calendar events visible to this user's branch.
        $calendarEvents = CalendarEvent::with(['matter', 'assignee', 'branch'])
            ->forBranchOf($user)
            ->whereBetween('starts_at', [$rangeStart, $rangeEnd])
            ->get()
            ->map(fn (CalendarEvent $event) => [
                'date' => $event->starts_at->toDateString(),
                'time' => $event->all_day ? null : $event->starts_at->format('H:i'),
                'label' => $event->title,
                'kind' => 'calendar',
                'status' => $event->status,
                'url' => route('calendar.show', $event),
            ]);

        // Court events visible by branch (via the parent matter's branch).
        $courtEvents = CourtEvent::with(['matter'])
            ->whereBetween('starts_at', [$rangeStart, $rangeEnd])
            ->when(! $user->canSeeAllBranches(), function ($query) use ($user) {
                $query->where(function ($query) use ($user) {
                    $query->where('assigned_to', $user->id);

                    $query->orWhereHas('matter', function ($query) use ($user) {
                        $query->whereNull('branch_id');

                        if ($user->branch_id) {
                            $query->orWhere('branch_id', $user->branch_id);
                        }
                    });
                });
            })
            ->get()
            ->map(fn (CourtEvent $event) => [
                'date' => $event->starts_at?->toDateString(),
                'time' => $event->starts_at?->format('H:i'),
                'label' => ($event->matter?->reference_no ? $event->matter->reference_no.' ' : '').$event->eventTypeLabel(),
                'kind' => 'court',
                'status' => $event->status,
                'url' => route('litigation.show', $event),
            ]);

        $events = $calendarEvents
            ->merge($courtEvents)
            ->filter(fn ($event) => $event['date'] !== null)
            ->groupBy('date');

        $holidays = PublicHoliday::forMonth($month->year, $month->month);

        $gridStart = $month->copy()->startOfMonth()->startOfWeek();
        $gridEnd = $month->copy()->endOfMonth()->endOfWeek();

        $days = [];
        for ($date = $gridStart->copy(); $date->lte($gridEnd); $date->addDay()) {
            $days[] = $date->copy();
        }

        return view('modules.calendar.index', [
            'month' => $month,
            'days' => $days,
            'events' => $events,
            'holidays' => $holidays,
            'canManageAll' => $user->canSeeAllBranches(),
        ]);
    }

    public function create(Request $request)
    {
        return view('modules.calendar.create', [
            'types' => CalendarEvent::TYPES,
            'branches' => Branch::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'matters' => Matter::orderByDesc('id')->limit(300)->get(['id', 'reference_no', 'title']),
            'officers' => User::orderBy('name')->get(['id', 'name']),
            'defaultDate' => $request->filled('date') ? $request->string('date')->toString() : now()->toDateString(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateEvent($request);
        $data['created_by'] = $request->user()->id;
        $data['branch_id'] = $data['branch_id'] ?? $request->user()->branch_id;

        $event = CalendarEvent::create($data);

        return redirect()
            ->route('calendar.show', $event)
            ->with('status', 'Event added to the calendar.');
    }

    public function show(CalendarEvent $calendar)
    {
        return view('modules.calendar.show', [
            'event' => $calendar->load(['matter', 'branch', 'assignee', 'creator']),
        ]);
    }

    public function edit(CalendarEvent $calendar)
    {
        return view('modules.calendar.edit', [
            'event' => $calendar,
            'types' => CalendarEvent::TYPES,
            'statuses' => CalendarEvent::STATUSES,
            'branches' => Branch::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'matters' => Matter::orderByDesc('id')->limit(300)->get(['id', 'reference_no', 'title']),
            'officers' => User::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, CalendarEvent $calendar)
    {
        $data = $this->validateEvent($request, true);

        $calendar->update($data);

        return redirect()
            ->route('calendar.show', $calendar)
            ->with('status', 'Event updated.');
    }

    public function destroy(CalendarEvent $calendar)
    {
        $calendar->delete();

        return redirect()
            ->route('calendar.index')
            ->with('status', 'Event removed.');
    }

    private function resolveMonth(Request $request): Carbon
    {
        if ($request->filled('m') && $request->filled('y')) {
            return Carbon::create($request->integer('y'), $request->integer('m'), 1)->startOfMonth();
        }

        if ($request->filled('month')) {
            return Carbon::createFromFormat('Y-m', $request->string('month')->toString())->startOfMonth();
        }

        return now()->startOfMonth();
    }

    private function validateEvent(Request $request, bool $withStatus = false): array
    {
        $rules = [
            'title' => ['required', 'string', 'max:191'],
            'type' => ['required', 'in:'.implode(',', array_keys(CalendarEvent::TYPES))],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'matter_id' => ['nullable', 'exists:matters,id'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'location' => ['nullable', 'string', 'max:191'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'all_day' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];

        if ($withStatus) {
            $rules['status'] = ['required', 'in:'.implode(',', array_keys(CalendarEvent::STATUSES))];
        }

        $data = $request->validate($rules);
        $data['all_day'] = $request->boolean('all_day');

        return $data;
    }
}
