<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientIntake;
use App\Models\PracticeArea;
use App\Models\User;
use App\Support\MonthlyReferenceNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ClientIntakeController extends Controller
{
    public function index(Request $request)
    {
        $reviewQueueStatuses = ['pending_review', 'rejected', 'more_information_needed'];
        $reviewQueueDecisions = ['pending', 'rejected', 'more_information_needed'];

        $intakes = ClientIntake::with(['practiceArea', 'preferredLawyer'])
            ->whereIn('status', $reviewQueueStatuses)
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search) {
                    $query
                        ->where('intake_no', 'like', "%{$search}%")
                        ->orWhere('client_name', 'like', "%{$search}%")
                        ->orWhere('legal_issue', 'like', "%{$search}%");
                });
            })
            ->when(
                in_array($request->string('status')->toString(), $reviewQueueStatuses, true),
                fn ($query) => $query->where('status', $request->string('status')->toString())
            )
            ->when(
                in_array($request->string('review_decision')->toString(), $reviewQueueDecisions, true),
                fn ($query) => $query->where('review_decision', $request->string('review_decision')->toString())
            )
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('modules.intakes.index', [
            'intakes' => $intakes,
            'filters' => $request->only(['search', 'status', 'review_decision']),
            'statuses' => collect(ClientIntake::STATUSES)->only($reviewQueueStatuses)->all(),
            'reviewDecisions' => collect(ClientIntake::REVIEW_DECISIONS)->only($reviewQueueDecisions)->all(),
        ]);
    }

    public function create()
    {
        return view('modules.intakes.create', [
            'intakeNumber' => MonthlyReferenceNumber::make(ClientIntake::class, 'intake_no', 'CI'),
            'practiceAreas' => PracticeArea::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'users' => $this->staffAdvocates()->get(),
            'urgencies' => ClientIntake::URGENCIES,
            'referralSources' => ClientIntake::REFERRAL_SOURCES,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'client_type' => ['required', Rule::in(['individual', 'organization'])],
            'client_name' => ['required', 'string', 'max:191'],
            'organization_name' => ['nullable', 'required_if:client_type,organization', 'string', 'max:191'],
            'email' => ['nullable', 'email', 'max:191'],
            'phone' => ['nullable', 'string', 'max:60'],
            'address' => ['nullable', 'string', 'max:1000'],
            'legal_issue' => ['required', 'string', 'max:191'],
            'practice_area_id' => ['nullable', 'exists:practice_areas,id'],
            'preferred_lawyer_id' => ['nullable', 'exists:users,id'],
            'urgency' => ['required', Rule::in(array_keys(ClientIntake::URGENCIES))],
            'referral_source' => ['nullable', Rule::in(array_keys(ClientIntake::REFERRAL_SOURCES))],
            'referral_name' => ['nullable', 'string', 'max:191'],
            'referral_contact' => [
                'nullable',
                'required_if:referral_source,email',
                'max:191',
                Rule::when($request->input('referral_source') === 'email', ['email']),
            ],
            'summary' => ['nullable', 'string', 'max:3000'],
            'consultation_on' => ['nullable', 'date'],
            'consultation_at' => ['nullable', 'date_format:H:i'],
            'conflict_parties' => ['nullable', 'array'],
            'conflict_parties.*.name' => ['nullable', 'string', 'max:191'],
            'conflict_parties.*.relationship' => ['nullable', 'string', 'max:191'],
            'conflict_parties.*.contact' => ['nullable', 'string', 'max:191'],
            'conflict_parties.*.notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if (filled($data['preferred_lawyer_id'] ?? null) && ! $this->staffAdvocates()->whereKey($data['preferred_lawyer_id'])->exists()) {
            throw ValidationException::withMessages([
                'preferred_lawyer_id' => 'The preferred advocate must be an active staff member.',
            ]);
        }

        $intake = DB::transaction(function () use ($data) {
            $conflictParties = collect($data['conflict_parties'] ?? [])
                ->filter(fn ($party) => filled($party['name'] ?? null))
                ->values();

            $intake = ClientIntake::create(collect($data)
                ->except('conflict_parties')
                ->merge([
                    'intake_no' => MonthlyReferenceNumber::make(ClientIntake::class, 'intake_no', 'CI'),
                    'organization_name' => ($data['client_type'] ?? null) === 'organization' ? ($data['organization_name'] ?? null) : null,
                    'created_by' => auth()->id(),
                    'status' => 'pending_review',
                    'review_decision' => 'pending',
                ])
                ->toArray());

            $conflictParties->each(fn ($party) => $intake->conflictParties()->create($party));

            return $intake;
        });

        return redirect()
            ->route('intakes.index')
            ->with('status', 'Client intake recorded. Review it from the intake register.');
    }

    public function show(ClientIntake $intake)
    {
        return view('modules.intakes.show', [
            'intake' => $intake->load(['practiceArea', 'preferredLawyer', 'creator', 'reviewer', 'client', 'conflictParties']),
            'reviewDecisions' => ClientIntake::REVIEW_DECISIONS,
        ]);
    }

    public function review(Request $request, ClientIntake $intake)
    {
        if (in_array($intake->review_decision, ['approved', 'rejected'], true)) {
            return redirect()
                ->route('intakes.show', $intake)
                ->with('status', 'This intake already has a final review decision.');
        }

        $data = $request->validate([
            'review_decision' => ['required', Rule::in(['approved', 'rejected', 'more_information_needed'])],
            'review_notes' => ['required', 'string', 'max:3000'],
        ]);

        $client = DB::transaction(function () use ($data, $intake) {
            $client = null;

            if ($data['review_decision'] === 'approved') {
                $client = $intake->client ?: Client::create([
                    'client_no' => MonthlyReferenceNumber::make(Client::class, 'client_no', 'CL'),
                    'client_type' => $intake->client_type,
                    'is_prospect' => false,
                    'name' => $intake->client_name,
                    'organization_name' => $intake->client_type === 'organization' ? ($intake->organization_name ?: $intake->client_name) : null,
                    'first_name' => $intake->client_type === 'individual' ? $intake->client_name : null,
                    'email' => $intake->email,
                    'phone' => $intake->phone,
                    'address' => $intake->address,
                    'client_in_charge_id' => $intake->preferred_lawyer_id,
                    'status' => 'active',
                ]);
            }

            $intake->update([
                'client_id' => $client?->id ?? $intake->client_id,
                'review_decision' => $data['review_decision'],
                'review_notes' => $data['review_notes'],
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
                'status' => $data['review_decision'] === 'approved' ? 'approved' : $data['review_decision'],
            ]);

            return $client;
        });

        if ($client) {
            return redirect()
                ->route('clients.index')
                ->with('status', 'Client intake approved. The client is now in the approved client register.');
        }

        return redirect()
            ->route('intakes.index')
            ->with('status', 'Client intake review saved.');
    }

    private function staffAdvocates()
    {
        return User::query()
            ->where(function ($query) {
                $query
                    ->whereNull('account_type')
                    ->orWhere('account_type', 'staff');
            })
            ->whereHas('staffProfile', fn ($query) => $query->where('employment_status', 'active'))
            ->orderBy('name');
    }

}
