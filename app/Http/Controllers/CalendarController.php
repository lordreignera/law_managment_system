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
use Illuminate\Validation\ValidationException;

class CalendarController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $month = $this->resolveMonth($request);
        $rangeStart = $month->copy()->startOfMonth();
        $rangeEnd = $month->copy()->endOfMonth();

        // General calendar events visible to this user's branch.
        $calendarEvents = CalendarEvent::with(['matter', 'assignee', 'branch', 'branches'])
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

        // Court entries follow the same matter visibility rules as Litigation.
        $courtEvents = CourtEvent::with(['matter'])
            ->whereBetween('starts_at', [$rangeStart, $rangeEnd])
            ->whereHas('matter', fn ($query) => $query->visibleTo($user))
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
            'matters' => Matter::visibleTo($request->user())->orderByDesc('id')->limit(300)->get(['id', 'reference_no', 'title']),
            'officers' => $this->calendarUsers(),
            'defaultBranchId' => $request->user()->branch_id,
            'defaultDate' => $request->filled('date') ? $request->string('date')->toString() : now()->toDateString(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateEvent($request);
        $data['created_by'] = $request->user()->id;
        $branchIds = $this->resolveBranchIds($data, $request);
        $assigneeIds = $this->resolveAssigneeIds($data, $branchIds);

        $event = CalendarEvent::create($data);
        $event->branches()->sync($branchIds);
        $event->assignees()->sync($assigneeIds);

        return redirect()
            ->route('calendar.show', $event)
            ->with('status', 'Event added to the calendar.');
    }

    public function show(CalendarEvent $calendar)
    {
        $this->ensureVisible($calendar, request()->user());

        return view('modules.calendar.show', [
            'event' => $calendar->load(['matter', 'branch', 'branches', 'assignee', 'assignees', 'creator']),
        ]);
    }

    public function edit(CalendarEvent $calendar)
    {
        $this->ensureVisible($calendar, request()->user());

        return view('modules.calendar.edit', [
            'event' => $calendar->load(['branches', 'assignees']),
            'types' => CalendarEvent::TYPES,
            'statuses' => CalendarEvent::STATUSES,
            'branches' => Branch::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'matters' => Matter::visibleTo(request()->user())->orderByDesc('id')->limit(300)->get(['id', 'reference_no', 'title']),
            'officers' => $this->calendarUsers(),
        ]);
    }

    public function update(Request $request, CalendarEvent $calendar)
    {
        $this->ensureVisible($calendar, $request->user());

        $data = $this->validateEvent($request, true);
        $branchIds = $this->resolveBranchIds($data, $request);
        $assigneeIds = $this->resolveAssigneeIds($data, $branchIds);

        $calendar->update($data);
        $calendar->branches()->sync($branchIds);
        $calendar->assignees()->sync($assigneeIds);

        return redirect()
            ->route('calendar.show', $calendar)
            ->with('status', 'Event updated.');
    }

    public function destroy(CalendarEvent $calendar)
    {
        $this->ensureVisible($calendar, request()->user());

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
            'branch_ids' => ['nullable', 'array'],
            'branch_ids.*' => ['integer', 'distinct', 'exists:branches,id'],
            'matter_id' => ['nullable', 'exists:matters,id'],
            'assigned_to' => ['nullable', 'array'],
            'assigned_to.*' => ['integer', 'distinct', 'exists:users,id'],
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

        if (! empty($data['matter_id']) && ! Matter::visibleTo($request->user())->whereKey($data['matter_id'])->exists()) {
            throw ValidationException::withMessages([
                'matter_id' => 'Select a matter you are allowed to view.',
            ]);
        }

        return $data;
    }

    private function ensureVisible(CalendarEvent $calendar, User $user): void
    {
        abort_unless(
            CalendarEvent::forBranchOf($user)->whereKey($calendar->id)->exists()
            && (! $calendar->matter_id || Matter::visibleTo($user)->whereKey($calendar->matter_id)->exists()),
            404
        );
    }

    private function resolveBranchIds(array &$data, Request $request): array
    {
        $branchIds = collect($data['branch_ids'] ?? [])
            ->filter()
            ->map(fn ($branchId) => (int) $branchId)
            ->unique()
            ->values();

        if ($branchIds->isEmpty() && $request->user()->branch_id) {
            $branchIds->push((int) $request->user()->branch_id);
        }

        $data['branch_id'] = $branchIds->first();
        unset($data['branch_ids']);

        return $branchIds->all();
    }

    private function resolveAssigneeIds(array &$data, array $branchIds): array
    {
        $assigneeIds = collect($data['assigned_to'] ?? [])
            ->filter()
            ->map(fn ($userId) => (int) $userId)
            ->unique()
            ->values();

        if ($assigneeIds->isEmpty()) {
            $data['assigned_to'] = null;

            return [];
        }

        $validIds = User::query()
            ->whereIn('id', $assigneeIds)
            ->whereIn('branch_id', $branchIds)
            ->pluck('id')
            ->map(fn ($userId) => (int) $userId);

        if ($validIds->count() !== $assigneeIds->count()) {
            throw ValidationException::withMessages([
                'assigned_to' => 'Each assigned user must belong to one of the selected branches.',
            ]);
        }

        $data['assigned_to'] = $assigneeIds->first();

        return $assigneeIds->all();
    }

    private function calendarUsers()
    {
        return User::query()
            ->whereNotNull('branch_id')
            ->with('branch:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'branch_id']);
    }
}
