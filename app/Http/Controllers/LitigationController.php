<?php

namespace App\Http\Controllers;

use App\Models\Court;
use App\Models\CourtEvent;
use App\Models\Matter;
use App\Models\User;
use Illuminate\Http\Request;

class LitigationController extends Controller
{
    public function index(Request $request)
    {
        $events = CourtEvent::with(['matter', 'court', 'assignee'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search) {
                    $query
                        ->where('case_number', 'like', "%{$search}%")
                        ->orWhere('court_name', 'like', "%{$search}%")
                        ->orWhere('judicial_officer', 'like', "%{$search}%")
                        ->orWhereHas('matter', fn ($query) => $query->where('reference_no', 'like', "%{$search}%")->orWhere('title', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('event_type'), fn ($query) => $query->where('event_type', $request->string('event_type')->toString()))
            ->when($request->filled('assigned_to'), fn ($query) => $query->where('assigned_to', $request->integer('assigned_to')))
            ->when($request->boolean('mine'), fn ($query) => $query->where('assigned_to', $request->user()->id))
            ->orderBy('starts_at')
            ->paginate(20)
            ->withQueryString();

        return view('modules.litigation.index', [
            'events' => $events,
            'filters' => $request->only(['search', 'status', 'event_type', 'assigned_to', 'mine']),
            'statuses' => CourtEvent::STATUSES,
            'eventTypes' => CourtEvent::EVENT_TYPES,
            'officers' => User::orderBy('name')->get(['id', 'name']),
            'summary' => [
                'Today' => CourtEvent::open()->whereDate('starts_at', today())->count(),
                'This Week' => CourtEvent::open()->whereBetween('starts_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
                'Overdue' => CourtEvent::where('status', 'scheduled')->where('starts_at', '<', now())->count(),
                'Completed' => CourtEvent::where('status', 'completed')->count(),
            ],
        ]);
    }

    public function create()
    {
        return view('modules.litigation.create', [
            'matters' => Matter::orderByDesc('id')->limit(300)->get(['id', 'reference_no', 'title']),
            'courts' => Court::orderBy('name')->get(['id', 'name']),
            'officers' => User::orderBy('name')->get(['id', 'name']),
            'statuses' => CourtEvent::STATUSES,
            'eventTypes' => CourtEvent::EVENT_TYPES,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateEvent($request);

        $event = CourtEvent::create($data);

        if ($request->hasFile('attachment')) {
            $event->addAttachment($request->file('attachment'), ['category' => 'court-document']);
        }

        return redirect()
            ->route('litigation.show', $event)
            ->with('status', 'Court event scheduled.');
    }

    public function show(CourtEvent $litigation)
    {
        return view('modules.litigation.show', [
            'event' => $litigation->load(['matter', 'court', 'assignee', 'attachments']),
            'statuses' => CourtEvent::STATUSES,
        ]);
    }

    public function edit(CourtEvent $litigation)
    {
        return view('modules.litigation.edit', [
            'event' => $litigation,
            'matters' => Matter::orderByDesc('id')->limit(300)->get(['id', 'reference_no', 'title']),
            'courts' => Court::orderBy('name')->get(['id', 'name']),
            'officers' => User::orderBy('name')->get(['id', 'name']),
            'statuses' => CourtEvent::STATUSES,
            'eventTypes' => CourtEvent::EVENT_TYPES,
        ]);
    }

    public function update(Request $request, CourtEvent $litigation)
    {
        $data = $this->validateEvent($request);

        $litigation->update($data);

        if ($request->hasFile('attachment')) {
            $litigation->addAttachment($request->file('attachment'), ['category' => 'court-document']);
        }

        return redirect()
            ->route('litigation.show', $litigation)
            ->with('status', 'Court event updated.');
    }

    public function recordOutcome(Request $request, CourtEvent $litigation)
    {
        $data = $request->validate([
            'status' => ['required', 'in:'.implode(',', array_keys(CourtEvent::STATUSES))],
            'outcome' => ['nullable', 'string', 'max:2000'],
            'next_step' => ['nullable', 'string', 'max:255'],
            'next_step_due' => ['nullable', 'date'],
        ]);

        $litigation->update($data);

        return back()->with('status', 'Outcome recorded.');
    }

    private function validateEvent(Request $request): array
    {
        $data = $request->validate([
            'matter_id' => ['required', 'exists:matters,id'],
            'court_id' => ['nullable', 'exists:courts,id'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'court_name' => ['nullable', 'string', 'max:255'],
            'case_number' => ['nullable', 'string', 'max:255'],
            'judicial_officer' => ['nullable', 'string', 'max:255'],
            'event_type' => ['required', 'in:'.implode(',', array_keys(CourtEvent::EVENT_TYPES))],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'status' => ['required', 'in:'.implode(',', array_keys(CourtEvent::STATUSES))],
            'notes' => ['nullable', 'string', 'max:2000'],
            'next_step' => ['nullable', 'string', 'max:255'],
            'next_step_due' => ['nullable', 'date'],
            'attachment' => ['nullable', 'file', 'max:5120'],
        ]);

        unset($data['attachment']);

        return $data;
    }
}
