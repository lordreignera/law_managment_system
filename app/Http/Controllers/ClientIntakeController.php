<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientIntake;
use App\Models\Engagement;
use App\Models\Matter;
use App\Models\PracticeArea;
use App\Models\User;
use App\Support\MonthlyReferenceNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ClientIntakeController extends Controller
{
    public function index(Request $request)
    {
        $intakes = ClientIntake::with(['practiceArea', 'preferredLawyer', 'convertedMatter'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search) {
                    $query
                        ->where('intake_no', 'like', "%{$search}%")
                        ->orWhere('client_name', 'like', "%{$search}%")
                        ->orWhere('legal_issue', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('conflict_status'), fn ($query) => $query->where('conflict_status', $request->string('conflict_status')->toString()))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('modules.intakes.index', [
            'intakes' => $intakes,
            'filters' => $request->only(['search', 'status', 'conflict_status']),
            'statuses' => ClientIntake::STATUSES,
            'conflictStatuses' => ClientIntake::CONFLICT_STATUSES,
        ]);
    }

    public function create()
    {
        return view('modules.intakes.create', [
            'intakeNumber' => MonthlyReferenceNumber::make(ClientIntake::class, 'intake_no', 'CI'),
            'practiceAreas' => PracticeArea::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'users' => User::orderBy('name')->get(),
            'urgencies' => ClientIntake::URGENCIES,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'client_type' => ['required', Rule::in(['individual', 'organization'])],
            'client_name' => ['required', 'string', 'max:191'],
            'organization_name' => ['nullable', 'string', 'max:191'],
            'email' => ['nullable', 'email', 'max:191'],
            'phone' => ['nullable', 'string', 'max:60'],
            'address' => ['nullable', 'string', 'max:1000'],
            'legal_issue' => ['required', 'string', 'max:191'],
            'practice_area_id' => ['nullable', 'exists:practice_areas,id'],
            'preferred_lawyer_id' => ['nullable', 'exists:users,id'],
            'urgency' => ['required', Rule::in(array_keys(ClientIntake::URGENCIES))],
            'referral_source' => ['nullable', 'string', 'max:191'],
            'summary' => ['nullable', 'string', 'max:3000'],
            'consultation_on' => ['nullable', 'date'],
            'consultation_at' => ['nullable', 'date_format:H:i'],
            'conflict_parties' => ['nullable', 'array'],
            'conflict_parties.*.name' => ['nullable', 'string', 'max:191'],
            'conflict_parties.*.relationship' => ['nullable', 'string', 'max:191'],
            'conflict_parties.*.notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $intake = DB::transaction(function () use ($data) {
            $conflictParties = collect($data['conflict_parties'] ?? [])
                ->filter(fn ($party) => filled($party['name'] ?? null))
                ->values();

            $intake = ClientIntake::create(collect($data)
                ->except('conflict_parties')
                ->merge([
                    'intake_no' => MonthlyReferenceNumber::make(ClientIntake::class, 'intake_no', 'CI'),
                    'created_by' => auth()->id(),
                    'status' => filled($data['consultation_on'] ?? null) ? 'consultation' : 'inquiry',
                    'conflict_status' => 'pending',
                ])
                ->toArray());

            $conflictParties->each(fn ($party) => $intake->conflictParties()->create($party));

            return $intake;
        });

        return redirect()
            ->route('intakes.show', $intake)
            ->with('status', 'Client intake recorded.');
    }

    public function show(ClientIntake $intake)
    {
        return view('modules.intakes.show', [
            'intake' => $intake->load(['practiceArea', 'preferredLawyer', 'creator', 'reviewer', 'convertedMatter', 'conflictParties']),
            'conflictStatuses' => ClientIntake::CONFLICT_STATUSES,
        ]);
    }

    public function reviewConflict(Request $request, ClientIntake $intake)
    {
        $data = $request->validate([
            'conflict_status' => ['required', Rule::in(array_keys(ClientIntake::CONFLICT_STATUSES))],
            'conflict_notes' => ['required', 'string', 'max:3000'],
        ]);

        $status = match ($data['conflict_status']) {
            'cleared' => 'engagement_pending',
            'conflict_found' => 'rejected',
            default => 'conflict_check',
        };

        $intake->update([
            'conflict_status' => $data['conflict_status'],
            'conflict_notes' => $data['conflict_notes'],
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'status' => $status,
        ]);

        return back()->with('status', 'Conflict review saved.');
    }

    public function convertToMatter(ClientIntake $intake)
    {
        abort_unless($intake->conflict_status === 'cleared', 422, 'Only cleared intakes can be converted to matters.');
        abort_if($intake->converted_matter_id, 422, 'This intake has already been converted.');

        $matter = DB::transaction(function () use ($intake) {
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

            $matter = Matter::create([
                'client_id' => $client->id,
                'practice_area_id' => $intake->practice_area_id,
                'opened_by' => auth()->id(),
                'branch_id' => auth()->user()->branch_id,
                'department_id' => auth()->user()->department_id,
                'reference_no' => MonthlyReferenceNumber::make(Matter::class, 'reference_no', 'MT'),
                'title' => $intake->legal_issue,
                'opened_on' => now()->toDateString(),
                'status' => 'engagement_pending',
                'description' => $intake->summary ?: $intake->legal_issue,
            ]);

            Engagement::create([
                'client_id' => $client->id,
                'matter_id' => $matter->id,
                'created_by' => auth()->id(),
                'engagement_no' => MonthlyReferenceNumber::make(Engagement::class, 'engagement_no', 'EG'),
                'title' => $matter->title,
                'status' => 'pending',
            ]);

            $intake->update([
                'client_id' => $client->id,
                'converted_matter_id' => $matter->id,
                'status' => 'engagement_pending',
            ]);

            return $matter;
        });

        return redirect()
            ->route('matters.index')
            ->with('status', 'Intake converted to matter '.$matter->reference_no.'.');
    }

}
