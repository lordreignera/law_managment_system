<?php

namespace App\Http\Controllers;

use App\Exports\LitigationExport;
use App\Imports\LitigationImport;
use App\Models\Court;
use App\Models\CourtEvent;
use App\Models\JudicialOfficer;
use App\Models\Matter;
use App\Models\User;
use App\Support\Litigation\LitigationQueryFilters;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class LitigationController extends Controller
{
    public function export(Request $request)
    {
        $filters = $this->filters($request);

        return Excel::download(
            new LitigationExport($filters, $request->user()),
            'litigation-'.now()->format('Ymd-His').'.xlsx'
        );
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ]);

        $import = new LitigationImport($request->user());
        Excel::import($import, $request->file('file'));

        return redirect()
            ->route('litigation.index')
            ->with('status', "Imported {$import->imported} court event(s); skipped {$import->skipped}.");
    }

    public function dashboard(Request $request)
    {
        $user = $request->user();
        $litigationMatters = $this->litigationMatterQuery($user);

        $myEvents = $this->scopedEvents($user)->with(['matter', 'court'])
            ->where('assigned_to', $user->id)
            ->open()
            ->orderBy('starts_at')
            ->limit(8)
            ->get();

        $upcomingEvents = $this->scopedEvents($user)->with(['matter', 'court', 'assignee'])
            ->open()
            ->whereDate('starts_at', '>=', today())
            ->orderBy('starts_at')
            ->limit(8)
            ->get();

        $nextSteps = $this->scopedEvents($user)->with(['matter', 'assignee'])
            ->whereNotNull('next_step')
            ->whereNotNull('next_step_due')
            ->whereIn('status', ['scheduled', 'adjourned', 'completed'])
            ->orderBy('next_step_due')
            ->limit(8)
            ->get();

        return view('modules.litigation.dashboard', [
            'stats' => [
                'My Open Events' => $this->scopedEvents($user)->open()->where('assigned_to', $user->id)->count(),
                'Today' => $this->scopedEvents($user)->open()->whereDate('starts_at', today())->count(),
                'This Week' => $this->scopedEvents($user)->open()->whereBetween('starts_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
                'Overdue' => $this->scopedEvents($user)->overdue()->count(),
            ],
            'lifecycle' => $this->litigationLifecycleSummary($litigationMatters, $user),
            'myEvents' => $myEvents,
            'upcomingEvents' => $upcomingEvents,
            'nextSteps' => $nextSteps,
        ]);
    }

    public function index(Request $request)
    {
        $filters = $this->filters($request);

        $events = LitigationQueryFilters::apply(
            $this->scopedEvents($request->user())->with(['matter', 'court', 'assignee']),
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
                'Today' => $this->scopedEvents($request->user())->open()->whereDate('starts_at', today())->count(),
                'This Week' => $this->scopedEvents($request->user())->open()->whereBetween('starts_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
                'Overdue' => $this->scopedEvents($request->user())->overdue()->count(),
                'Completed' => $this->scopedEvents($request->user())->where('status', 'completed')->count(),
            ],
        ]);
    }

    public function create(Request $request)
    {
        return view('modules.litigation.create', [
            'matters' => Matter::visibleTo($request->user())
                ->whereNotIn('status', ['closed', 'archived'])
                ->orderByDesc('id')
                ->limit(300)
                ->get(['id', 'reference_no', 'title']),
            'courts' => Court::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'judicialOfficers' => JudicialOfficer::where('is_active', true)->with('court')->orderBy('sort_order')->orderBy('name')->get(),
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

        $event = DB::transaction(function () use ($data, $request) {
            $event = CourtEvent::create($data);
            $matter = $event->matter;

            if (in_array($matter->status, ['open', 'planning'], true)) {
                $matter->update(['status' => 'active']);
            }

            if ($request->hasFile('attachment')) {
                $event->addAttachment($request->file('attachment'), ['category' => 'court-document']);
            }

            return $event;
        });

        return redirect()
            ->route('litigation.show', $event)
            ->with('status', 'Court event scheduled.');
    }

    public function show(CourtEvent $litigation)
    {
        $this->ensureEventVisible($litigation, request()->user());

        return view('modules.litigation.show', [
            'event' => $litigation->load(['matter', 'court', 'judicialOfficer', 'assignee', 'attachments', 'reminderLogs.recipient']),
            'statuses' => CourtEvent::STATUSES,
        ]);
    }

    public function edit(CourtEvent $litigation)
    {
        $this->ensureEventVisible($litigation, request()->user());

        return view('modules.litigation.edit', [
            'event' => $litigation,
            'matters' => Matter::visibleTo(request()->user())
                ->where(function ($query) use ($litigation) {
                    $query->whereNotIn('status', ['closed', 'archived'])
                        ->orWhere('id', $litigation->matter_id);
                })
                ->orderByDesc('id')
                ->limit(300)
                ->get(['id', 'reference_no', 'title']),
            'courts' => Court::where('is_active', true)->orWhere('id', $litigation->court_id)->orderBy('name')->get(['id', 'name']),
            'judicialOfficers' => JudicialOfficer::where('is_active', true)->orWhere('id', $litigation->judicial_officer_id)->with('court')->orderBy('sort_order')->orderBy('name')->get(),
            'officers' => $this->staffOfficers(),
            'statuses' => CourtEvent::STATUSES,
            'eventTypes' => CourtEvent::EVENT_TYPES,
        ]);
    }

    public function update(Request $request, CourtEvent $litigation)
    {
        $this->ensureEventVisible($litigation, $request->user());

        $data = $this->validateEvent($request, allowClosedMatter: true);

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
        $this->ensureEventVisible($litigation, $request->user());

        $data = $request->validate([
            'status' => ['required', 'in:'.implode(',', array_keys(CourtEvent::STATUSES))],
            'outcome' => ['nullable', 'string', 'max:2000'],
            'next_step' => ['nullable', 'string', 'max:255'],
            'next_step_due' => ['nullable', 'date'],
        ]);

        $litigation->update($data);

        return back()->with('status', 'Outcome recorded.');
    }

    private function validateEvent(Request $request, bool $allowClosedMatter = false): array
    {
        $data = $request->validate([
            'matter_id' => ['required', 'exists:matters,id'],
            'court_id' => ['nullable', 'exists:courts,id'],
            'judicial_officer_id' => ['nullable', 'exists:judicial_officers,id'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'court_name' => ['required_without:court_id', 'nullable', 'string', 'max:255'],
            'case_number' => ['nullable', 'string', 'max:255'],
            'judicial_officer' => ['nullable', 'string', 'max:255'],
            'event_type' => ['required', 'in:'.implode(',', array_keys(CourtEvent::EVENT_TYPES))],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'status' => ['required', 'in:'.implode(',', array_keys(CourtEvent::STATUSES))],
            'notes' => ['nullable', 'string', 'max:2000'],
            'next_step' => ['nullable', 'string', 'max:255'],
            'next_step_due' => ['nullable', 'date'],
            'reminder_days_before' => ['nullable', 'integer', 'min:0', 'max:30'],
            'escalation_days_after' => ['nullable', 'integer', 'min:0', 'max:30'],
            'reminders_enabled' => ['nullable', 'boolean'],
            'attachment' => ['nullable', 'file', 'max:5120'],
        ]);

        $data['reminder_days_before'] = (int) ($data['reminder_days_before'] ?? 1);
        $data['escalation_days_after'] = (int) ($data['escalation_days_after'] ?? 0);
        $data['reminders_enabled'] = $request->has('reminders_enabled') ? $request->boolean('reminders_enabled') : true;

        $matterQuery = Matter::visibleTo($request->user())->whereKey($data['matter_id']);

        if (! $allowClosedMatter) {
            $matterQuery->whereNotIn('status', ['closed', 'archived']);
        }

        if (! $matterQuery->exists()) {
            throw ValidationException::withMessages([
                'matter_id' => $allowClosedMatter
                    ? 'Select a matter in your branch.'
                    : 'Select an open matter in your branch. Reopen a closed matter before scheduling further court work.',
            ]);
        }

        if (! empty($data['assigned_to']) && ! $this->staffOfficerQuery()->whereKey($data['assigned_to'])->exists()) {
            throw ValidationException::withMessages([
                'assigned_to' => 'Select an active internal staff member.',
            ]);
        }

        if (! empty($data['judicial_officer_id']) && ! empty($data['court_id'])) {
            $officerCourtId = JudicialOfficer::whereKey($data['judicial_officer_id'])->value('court_id');
            if ($officerCourtId && (int) $officerCourtId !== (int) $data['court_id']) {
                throw ValidationException::withMessages([
                    'judicial_officer_id' => 'Select a judicial officer attached to the chosen court.',
                ]);
            }
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

    private function litigationMatterQuery(?User $user = null)
    {
        return Matter::visibleTo($user)
            ->where(function ($query) {
                $query
                    ->whereHas('practiceArea', fn ($query) => $query->where('name', 'like', '%Litigation%'))
                    ->orWhereHas('courtEvents');
            });
    }

    private function litigationLifecycleSummary($query, User $user): array
    {
        return [
            [
                'stage' => 'Matter Opening & Planning',
                'description' => 'Approved matters are opened, assigned, and prepared for legal work.',
                'count' => (clone $query)->whereIn('status', ['open', 'planning'])->count(),
                'route' => route('matters.index', ['litigation_stage' => 'opening']),
                'action' => 'Review Files',
                'icon' => 'mdi-folder-open-outline',
                'tone' => 'navy',
            ],
            [
                'stage' => 'Active Work & Client Follow-up',
                'description' => 'Advocates progress the instruction and track client or third-party dependencies.',
                'count' => (clone $query)->whereIn('status', ['active', 'waiting_for_client', 'waiting_for_third_party_or_court'])->count(),
                'route' => route('matters.index', ['litigation_stage' => 'review']),
                'action' => 'Review Work',
                'eventRoute' => route('litigation.create', ['event_type' => 'filing_deadline']),
                'eventAction' => 'Add Filing Deadline',
                'icon' => 'mdi-file-document-edit-outline',
                'tone' => 'gold',
            ],
            [
                'stage' => 'Filing, Service & Court Process',
                'description' => 'Court filing, summons/service, hearings, mentions, conferences, and submissions.',
                'count' => LitigationQueryFilters::apply($this->scopedEvents($user), ['stage' => 'court_process'])->count(),
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
                'count' => LitigationQueryFilters::apply($this->scopedEvents($user), ['stage' => 'judgment_ruling'])->count(),
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
                'count' => LitigationQueryFilters::apply($this->scopedEvents($user), ['stage' => 'taxation_execution'])->count(),
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

    private function scopedEvents(User $user)
    {
        return CourtEvent::whereHas('matter', fn ($query) => $query->visibleTo($user));
    }

    private function ensureEventVisible(CourtEvent $event, ?User $user): void
    {
        abort_unless($user && Matter::visibleTo($user)->whereKey($event->matter_id)->exists(), 404);
    }
}
