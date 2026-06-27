<?php

namespace App\Http\Controllers;

use App\Models\BusinessIndustry;
use App\Models\Client;
use App\Models\Engagement;
use App\Models\EngagementType;
use App\Models\Matter;
use App\Models\MatterCategory;
use App\Models\PracticeArea;
use App\Models\Shelf;
use App\Models\User;
use App\Support\MonthlyReferenceNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MatterController extends Controller
{
    public function index(Request $request)
    {
        $matters = Matter::with(['client', 'practiceArea'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search) {
                    $query
                        ->where('reference_no', 'like', "%{$search}%")
                        ->orWhere('title', 'like', "%{$search}%")
                        ->orWhereHas('client', fn ($query) => $query->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('modules.matters.index', [
            'matters' => $matters,
            'filters' => $request->only(['search', 'status']),
            'statuses' => Matter::STATUSES,
        ]);
    }

    public function show(Matter $matter)
    {
        return view('modules.matters.show', [
            'matter' => $matter->load(['client', 'practiceArea', 'engagement.engagementType', 'assignments.user']),
            'engagementTypes' => EngagementType::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('modules.matters.create', [
            'matterNumber' => MonthlyReferenceNumber::make(Matter::class, 'reference_no', 'MT'),
            'clients' => Client::orderBy('name')->get(),
            'practiceAreas' => PracticeArea::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'businessIndustries' => BusinessIndustry::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'matterCategories' => MatterCategory::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'shelves' => Shelf::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'users' => User::orderBy('name')->get(),
            'statuses' => Matter::STATUSES,
        ]);
    }

    public function updateEngagement(Request $request, Matter $matter)
    {
        abort_unless($matter->status === 'engagement_pending', 422, 'Only engagement-pending matters can be opened from engagement review.');

        $data = $request->validate([
            'engagement_type_id' => ['nullable', 'exists:engagement_types,id'],
            'engagement_letter_sent_on' => ['required', 'date'],
            'fee_agreement_sent_on' => ['required', 'date'],
            'retainer_required' => ['nullable', 'boolean'],
            'retainer_amount' => ['nullable', 'numeric', 'min:0', 'required_if:retainer_required,1'],
            'client_accepted_on' => ['required', 'date'],
            'engagement_notes' => ['nullable', 'string', 'max:3000'],
        ]);

        $engagement = $matter->engagement ?: Engagement::create([
            'client_id' => $matter->client_id,
            'matter_id' => $matter->id,
            'created_by' => auth()->id(),
            'engagement_no' => MonthlyReferenceNumber::make(Engagement::class, 'engagement_no', 'EG'),
            'title' => $matter->title,
            'status' => 'pending',
        ]);

        $engagement->update([
            'engagement_type_id' => $data['engagement_type_id'] ?? null,
            'engagement_letter_sent_on' => $data['engagement_letter_sent_on'],
            'fee_agreement_sent_on' => $data['fee_agreement_sent_on'],
            'retainer_required' => $request->boolean('retainer_required'),
            'retainer_amount' => $request->boolean('retainer_required') ? ($data['retainer_amount'] ?? 0) : null,
            'client_accepted_on' => $data['client_accepted_on'],
            'notes' => $data['engagement_notes'] ?? null,
            'status' => 'accepted',
        ]);

        $matter->update([
            'status' => 'open',
        ]);

        return redirect()
            ->route('matters.show', $matter)
            ->with('status', 'Engagement accepted. Matter is now open.');
    }

    public function createForClient(Client $client)
    {
        return view('modules.clients.engagement', [
            'client' => $client,
            'matterNumber' => MonthlyReferenceNumber::make(Matter::class, 'reference_no', 'MT'),
            'practiceAreas' => PracticeArea::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'businessIndustries' => BusinessIndustry::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'matterCategories' => MatterCategory::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'engagementTypes' => EngagementType::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'shelves' => Shelf::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'users' => User::orderBy('name')->get(),
        ]);
    }

    public function storeForClient(Request $request, Client $client)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:191'],
            'practice_area_id' => ['required', 'exists:practice_areas,id'],
            'business_industry_id' => ['nullable', 'exists:business_industries,id'],
            'matter_category_id' => ['nullable', 'exists:matter_categories,id'],
            'engagement_type_id' => ['nullable', 'exists:engagement_types,id'],
            'shelf_id' => ['nullable', 'exists:shelves,id'],
            'opened_on' => ['required', 'date'],
            'privacy_status' => ['required', Rule::in(['public', 'private'])],
            'opposite_counsel' => ['nullable', 'string', 'max:191'],
            'description' => ['required', 'string', 'max:3000'],
            'partner_ids' => ['required', 'array', 'min:1'],
            'partner_ids.*' => ['exists:users,id'],
            'associate_ids' => ['nullable', 'array'],
            'associate_ids.*' => ['exists:users,id'],
        ]);

        $matter = DB::transaction(function () use ($client, $data) {
            $partnerIds = $data['partner_ids'];
            $associateIds = $data['associate_ids'] ?? [];

            $matter = Matter::create(collect($data)
                ->except(['partner_ids', 'associate_ids', 'engagement_type_id'])
                ->merge([
                    'client_id' => $client->id,
                    'opened_by' => auth()->id(),
                    'branch_id' => auth()->user()->branch_id,
                    'department_id' => auth()->user()->department_id,
                    'reference_no' => MonthlyReferenceNumber::make(Matter::class, 'reference_no', 'MT'),
                    'status' => 'engagement_pending',
                ])
                ->toArray());

            Engagement::create([
                'client_id' => $client->id,
                'matter_id' => $matter->id,
                'engagement_type_id' => $data['engagement_type_id'] ?? null,
                'created_by' => auth()->id(),
                'engagement_no' => MonthlyReferenceNumber::make(Engagement::class, 'engagement_no', 'EG'),
                'title' => $matter->title,
                'status' => 'pending',
            ]);

            foreach ($partnerIds as $index => $userId) {
                $matter->assignments()->create([
                    'user_id' => $userId,
                    'assignment_role' => 'partner',
                    'assigned_on' => now()->toDateString(),
                    'is_lead' => $index === 0,
                ]);
            }

            foreach (array_diff($associateIds, $partnerIds) as $userId) {
                $matter->assignments()->create([
                    'user_id' => $userId,
                    'assignment_role' => 'associate',
                    'assigned_on' => now()->toDateString(),
                ]);
            }

            return $matter;
        });

        return redirect()
            ->route('matters.show', $matter)
            ->with('status', 'New engagement created for '.$client->display_name.'.');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:191'],
            'client_id' => ['required', 'exists:clients,id'],
            'ultimate_client_id' => ['nullable', 'exists:clients,id'],
            'practice_area_id' => ['required', 'exists:practice_areas,id'],
            'business_industry_id' => ['nullable', 'exists:business_industries,id'],
            'matter_category_id' => ['nullable', 'exists:matter_categories,id'],
            'shelf_id' => ['nullable', 'exists:shelves,id'],
            'opened_on' => ['required', 'date'],
            'closed_on' => ['nullable', 'date', 'after_or_equal:opened_on'],
            'status' => ['required', Rule::in(array_keys(Matter::STATUSES))],
            'privacy_status' => ['required', Rule::in(['public', 'private'])],
            'opposite_counsel' => ['nullable', 'string', 'max:191'],
            'description' => ['required', 'string', 'max:3000'],
            'partner_ids' => ['required', 'array', 'min:1'],
            'partner_ids.*' => ['exists:users,id'],
            'associate_ids' => ['nullable', 'array'],
            'associate_ids.*' => ['exists:users,id'],
        ]);

        DB::transaction(function () use ($data) {
            $partnerIds = $data['partner_ids'];
            $associateIds = $data['associate_ids'] ?? [];

            $matter = Matter::create(collect($data)
                ->except(['partner_ids', 'associate_ids'])
                ->merge([
                    'opened_by' => auth()->id(),
                    'branch_id' => auth()->user()->branch_id,
                    'department_id' => auth()->user()->department_id,
                    'reference_no' => MonthlyReferenceNumber::make(Matter::class, 'reference_no', 'MT'),
                ])
                ->toArray());

            foreach ($partnerIds as $index => $userId) {
                $matter->assignments()->create([
                    'user_id' => $userId,
                    'assignment_role' => 'partner',
                    'assigned_on' => now()->toDateString(),
                    'is_lead' => $index === 0,
                ]);
            }

            foreach (array_diff($associateIds, $partnerIds) as $userId) {
                $matter->assignments()->create([
                    'user_id' => $userId,
                    'assignment_role' => 'associate',
                    'assigned_on' => now()->toDateString(),
                ]);
            }
        });

        return redirect()
            ->route('matters.index')
            ->with('status', 'Matter created.');
    }

}
