<?php

namespace App\Http\Controllers;

use App\Models\Court;
use App\Models\CourtEvent;
use App\Models\Matter;
use App\Models\User;
use App\Exports\LitigationExport;
use App\Imports\LitigationImport;
use App\Support\Litigation\LitigationQueryFilters;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class LitigationController extends Controller
{
    public function export(Request $request)
    {
        $filters = $this->filters($request);

        return Excel::download(
            new LitigationExport($filters, $request->user()->id),
            'litigation-'.now()->format('Ymd-His').'.xlsx'
        );
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ]);

        $import = new LitigationImport();
        Excel::import($import, $request->file('file'));

        return redirect()
            ->route('litigation.index')
            ->with('status', "Imported {$import->imported} court event(s); skipped {$import->skipped}.");
    }

    public function dashboard(Request $request)
    {
        $user = $request->user();
        $litigationMatters = $this->litigationMatterQuery();

        $myEvents = CourtEvent::with(['matter', 'court'])
            ->where('assigned_to', $user->id)
            ->open()
            ->orderBy('starts_at')
            ->limit(8)
            ->get();

        $upcomingEvents = CourtEvent::with(['matter', 'court', 'assignee'])
            ->open()
            ->whereDate('starts_at', '>=', today())
            ->orderBy('starts_at')
            ->limit(8)
            ->get();

        $nextSteps = CourtEvent::with(['matter', 'assignee'])
            ->whereNotNull('next_step')
            ->whereNotNull('next_step_due')
            ->whereIn('status', ['scheduled', 'adjourned', 'completed'])
            ->orderBy('next_step_due')
            ->limit(8)
            ->get();

        return view('modules.litigation.dashboard', [
            'stats' => [
                'My Open Events' => CourtEvent::open()->where('assigned_to', $user->id)->count(),
                'Today' => CourtEvent::open()->whereDate('starts_at', today())->count(),
                'This Week' => CourtEvent::open()->whereBetween('starts_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
                'Overdue' => CourtEvent::where('status', 'scheduled')->where('starts_at', '<', now())->count(),
            ],
            'lifecycle' => $this->litigationLifecycleSummary($litigationMatters),
            'myEvents' => $myEvents,
            'upcomingEvents' => $upcomingEvents,
            'nextSteps' => $nextSteps,
        ]);
    }

    public function index(Request $request)
    {
        $filters = $this->filters($request);

        $events = LitigationQueryFilters::apply(
            CourtEvent::with(['matter', 'court', 'assignee']),
            $filters,
            $request->user()->id
        )
            ->orderBy('starts_at')
            ->paginate(20)
            ->withQueryString();

        return view('modules.litigation.index', [
            'events' => $events,
            'filters' => $filters,
            'statuses' => CourtEvent::STATUSES,
            'eventTypes' => CourtEvent::EVENT_TYPES,
            'stages' => LitigationQueryFilters::STAGES,
            'officers' => $this->staffOfficers(),
            'summary' => [
                'Today' => CourtEvent::open()->whereDate('starts_at', today())->count(),
                'This Week' => CourtEvent::open()->whereBetween('starts_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
                'Overdue' => CourtEvent::where('status', 'scheduled')->where('starts_at', '<', now())->count(),
                'Completed' => CourtEvent::where('status', 'completed')->count(),
            ],
        ]);
    }

    public function create(Request $request)
    {
        return view('modules.litigation.create', [
            'matters' => Matter::orderByDesc('id')->limit(300)->get(['id', 'reference_no', 'title']),
            'courts' => Court::orderBy('name')->get(['id', 'name']),
            'officers' => $this->staffOfficers(),
            'statuses' => CourtEvent::STATUSES,
            'eventTypes' => CourtEvent::EVENT_TYPES,
            'selectedMatterId' => $request->integer('matter_id') ?: null,
            'selectedEventType' => $request->string('event_type')->toString() ?: null,
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
            'officers' => $this->staffOfficers(),
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

        if (! empty($data['assigned_to']) && ! $this->staffOfficerQuery()->whereKey($data['assigned_to'])->exists()) {
            throw ValidationException::withMessages([
                'assigned_to' => 'Select an active internal staff member.',
            ]);
        }

        unset($data['attachment']);

        return $data;
    }

    private function filters(Request $request): array
    {
        return collect($request->only(['search', 'status', 'event_type', 'assigned_to', 'mine', 'stage']))
            ->filter(fn ($value) => filled($value))
            ->all();
    }

    private function staffOfficers()
    {
        return $this->staffOfficerQuery()
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function staffOfficerQuery()
    {
        return User::query()
            ->where(function ($query) {
                $query
                    ->whereNull('account_type')
                    ->orWhere('account_type', 'staff');
            })
            ->whereHas('staffProfile', fn ($query) => $query->where('employment_status', 'active'));
    }

    private function litigationMatterQuery()
    {
        return Matter::query()
            ->where(function ($query) {
                $query
                    ->whereHas('practiceArea', fn ($query) => $query->where('name', 'like', '%Litigation%'))
                    ->orWhereHas('courtEvents');
            });
    }

    private function litigationLifecycleSummary($query): array
    {
        return [
            [
                'stage' => 'Instructions & File Opening',
                'description' => 'Instructions received, accepted, documents requested, and internal file opened.',
                'count' => (clone $query)->whereIn('status', ['inquiry', 'consultation', 'conflict_check', 'file_pending'])->count(),
                'route' => route('matters.index', ['status' => 'file_pending']),
                'action' => 'Review Files',
                'icon' => 'mdi-folder-open-outline',
                'tone' => 'navy',
            ],
            [
                'stage' => 'Review, Opinion & Pleadings',
                'description' => 'Facts reviewed, legal opinion prepared, pleadings drafted, and client approval sought.',
                'count' => (clone $query)->whereIn('status', ['open', 'planning', 'waiting_for_client'])->count(),
                'route' => route('matters.index', ['status' => 'planning']),
                'action' => 'Review Work',
                'eventRoute' => route('litigation.create', ['event_type' => 'filing_deadline']),
                'eventAction' => 'Add Filing Deadline',
                'icon' => 'mdi-file-document-edit-outline',
                'tone' => 'gold',
            ],
            [
                'stage' => 'Filing, Service & Court Process',
                'description' => 'Court filing, summons/service, hearings, mentions, conferences, and submissions.',
                'count' => CourtEvent::open()->count(),
                'route' => route('litigation.index', ['stage' => 'court_process']),
                'exportRoute' => route('litigation.export', ['stage' => 'court_process']),
                'action' => 'Open Cause List',
                'eventRoute' => route('litigation.create', ['event_type' => 'hearing']),
                'eventAction' => 'Add Hearing',
                'icon' => 'mdi-gavel',
                'tone' => 'blue',
            ],
            [
                'stage' => 'Judgment / Ruling',
                'description' => 'Rulings and judgments delivered, with outcomes and next steps captured.',
                'count' => CourtEvent::where('status', 'completed')->whereIn('event_type', ['judgment', 'ruling'])->count(),
                'route' => route('litigation.index', ['stage' => 'judgment_ruling']),
                'exportRoute' => route('litigation.export', ['stage' => 'judgment_ruling']),
                'action' => 'View Outcomes',
                'eventRoute' => route('litigation.create', ['event_type' => 'judgment']),
                'eventAction' => 'Add Judgment',
                'icon' => 'mdi-scale-balance',
                'tone' => 'green',
            ],
            [
                'stage' => 'Taxation & Execution',
                'description' => 'Bill of costs, pre-taxation, taxation, garnishee, attachment, or committal follow-up.',
                'count' => CourtEvent::whereIn('event_type', LitigationQueryFilters::STAGES['taxation_execution']['event_types'])->count(),
                'route' => route('litigation.index', ['stage' => 'taxation_execution']),
                'exportRoute' => route('litigation.export', ['stage' => 'taxation_execution']),
                'action' => 'Track Execution',
                'eventRoute' => route('litigation.create', ['event_type' => 'taxation']),
                'eventAction' => 'Add Taxation',
                'icon' => 'mdi-cash-register',
                'tone' => 'red',
            ],
        ];
    }
}
