<?php

namespace App\Http\Controllers;

use App\Models\BusinessIndustry;
use App\Models\Client;
use App\Models\File;
use App\Models\InstructionType;
use App\Models\Matter;
use App\Models\MatterCategory;
use App\Models\OpposingCounsel;
use App\Models\PracticeArea;
use App\Models\Shelf;
use App\Models\User;
use App\Exports\MattersExport;
use App\Imports\MattersImport;
use App\Support\MonthlyReferenceNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class MatterController extends Controller
{
    public function dashboard(Request $request)
    {
        $user = $request->user();
        $baseQuery = Matter::query()->visibleTo($user);
        $stats = [
            [
                'label' => 'Total Matters',
                'value' => (clone $baseQuery)->count(),
                'icon' => 'mdi-briefcase-outline',
                'route' => route('matters.index'),
            ],
            [
                'label' => 'Open Matters',
                'value' => (clone $baseQuery)->where('status', 'open')->count(),
                'icon' => 'mdi-folder-open-outline',
                'route' => route('matters.index', ['status' => 'open']),
            ],
            [
                'label' => 'File Pending',
                'value' => (clone $baseQuery)->where('status', 'file_pending')->count(),
                'icon' => 'mdi-folder-clock-outline',
                'route' => route('matters.index', ['status' => 'file_pending']),
            ],
            [
                'label' => 'Active Work',
                'value' => (clone $baseQuery)->where('status', 'active')->count(),
                'icon' => 'mdi-briefcase-check-outline',
                'route' => route('matters.index', ['status' => 'active']),
            ],
            [
                'label' => 'Billing Pending',
                'value' => (clone $baseQuery)->where('status', 'billing_pending')->count(),
                'icon' => 'mdi-receipt-clock-outline',
                'route' => route('matters.index', ['status' => 'billing_pending']),
            ],
            [
                'label' => 'Under Review',
                'value' => (clone $baseQuery)->where('status', 'under_review')->count(),
                'icon' => 'mdi-clipboard-search-outline',
                'route' => route('matters.index', ['status' => 'under_review']),
            ],
        ];

        $statusRows = collect(Matter::STATUSES)->map(function (string $label, string $status) use ($baseQuery) {
            return [
                'label' => $label,
                'status' => $status,
                'count' => (clone $baseQuery)->where('status', $status)->count(),
                'route' => route('matters.index', ['status' => $status]),
            ];
        });

        $practiceAreaRows = PracticeArea::query()
            ->withCount([
                'matters as active_matters_count' => fn ($query) => $query
                    ->visibleTo($user)
                    ->whereNotIn('status', ['closed', 'archived']),
            ])
            ->orderByDesc('active_matters_count')
            ->orderBy('name')
            ->limit(8)
            ->get();

        $myMatters = Matter::with(['client', 'practiceArea'])
            ->visibleTo($user)
            ->where(function ($query) use ($user) {
                $query
                    ->where('opened_by', $user->id)
                    ->orWhereHas('assignments', fn ($query) => $query->where('user_id', $user->id));
            })
            ->latest()
            ->limit(8)
            ->get();

        $recentMatters = Matter::with(['client', 'practiceArea'])
            ->visibleTo($user)
            ->latest('updated_at')
            ->limit(8)
            ->get();

        return view('modules.matters.dashboard', [
            'stats' => $stats,
            'statusRows' => $statusRows,
            'practiceAreaRows' => $practiceAreaRows,
            'myMatters' => $myMatters,
            'recentMatters' => $recentMatters,
        ]);
    }

    public function index(Request $request)
    {
        $matters = Matter::with(['client', 'practiceArea'])
            ->visibleTo($request->user())
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
            ->when(in_array($request->query('litigation_stage'), ['opening', 'review'], true), function ($query) use ($request) {
                $statuses = $request->query('litigation_stage') === 'opening'
                    ? ['inquiry', 'consultation', 'conflict_check', 'file_pending']
                    : ['open', 'planning', 'waiting_for_client'];

                $query->whereIn('status', $statuses)
                    ->where(fn ($query) => $query
                        ->whereHas('practiceArea', fn ($query) => $query->where('name', 'like', '%Litigation%'))
                        ->orWhereHas('courtEvents'));
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('modules.matters.index', [
            'matters' => $matters,
            'filters' => $request->only(['search', 'status', 'litigation_stage']),
            'statuses' => Matter::STATUSES,
        ]);
    }

    public function export(Request $request)
    {
        return Excel::download(new MattersExport($request->user()), 'matters-'.now()->format('Ymd-His').'.xlsx');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ]);

        $import = new MattersImport($request->user());
        Excel::import($import, $request->file('file'));

        return redirect()
            ->route('matters.index')
            ->with('status', "Imported {$import->imported} matter(s); skipped {$import->skipped}.");
    }

    public function show(Matter $matter)
    {
        abort_unless(Matter::visibleTo(request()->user())->whereKey($matter->id)->exists(), 403);

        return view('modules.matters.show', [
            'matter' => $matter->load(['client', 'ultimateClient', 'practiceArea', 'instructionType', 'businessIndustry', 'matterCategory', 'shelf', 'opposingCounsel', 'files.billingType', 'files.attachments', 'assignments.user', 'courtEvents.court', 'courtEvents.judicialOfficer', 'courtEvents.assignee']),
        ]);
    }

    public function create()
    {
        return view('modules.matters.create', [
            'matterNumber' => MonthlyReferenceNumber::make(Matter::class, 'reference_no', 'MT'),
            'clients' => Client::forBranchOf(request()->user())->orderBy('name')->get(),
            'practiceAreas' => PracticeArea::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'instructionTypes' => InstructionType::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'businessIndustries' => BusinessIndustry::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'matterCategories' => MatterCategory::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'shelves' => Shelf::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'opposingCounsels' => OpposingCounsel::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'users' => $this->assignableUsers()->orderBy('name')->get(),
            'statuses' => Matter::STATUSES,
        ]);
    }

    public function createForClient(Client $client)
    {
        abort_unless(Client::forBranchOf(request()->user())->whereKey($client->id)->exists(), 404);

        if ($client->matter) {
            abort_unless(Matter::visibleTo(request()->user())->whereKey($client->matter->id)->exists(), 404);

            return redirect()
                ->route('matters.show', $client->matter)
                ->with('status', 'This client already has a matter.');
        }

        abort_if($client->files()->doesntExist(), 422, 'Open a file for this client before opening a matter.');

        return view('modules.matters.open', [
            'client' => $client,
            'matterNumber' => MonthlyReferenceNumber::make(Matter::class, 'reference_no', 'MT'),
            'practiceAreas' => PracticeArea::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'instructionTypes' => InstructionType::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'businessIndustries' => BusinessIndustry::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'matterCategories' => MatterCategory::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'shelves' => Shelf::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'opposingCounsels' => OpposingCounsel::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'users' => $this->assignableUsers()->orderBy('name')->get(),
            'suggestedTitle' => $client->files()->latest()->value('file_name'),
        ]);
    }

    public function edit(Request $request, Matter $matter)
    {
        abort_unless(Matter::visibleTo($request->user())->whereKey($matter->id)->exists(), 404);

        return view('modules.matters.create', [
            'matter' => $matter->load(['client', 'assignments']),
            'clients' => Client::forBranchOf($request->user())->orderBy('name')->get(),
            'practiceAreas' => PracticeArea::where('is_active', true)->orWhere('id', $matter->practice_area_id)->orderBy('sort_order')->orderBy('name')->get(),
            'instructionTypes' => InstructionType::where('is_active', true)->orWhere('id', $matter->instruction_type_id)->orderBy('sort_order')->orderBy('name')->get(),
            'businessIndustries' => BusinessIndustry::where('is_active', true)->orWhere('id', $matter->business_industry_id)->orderBy('sort_order')->orderBy('name')->get(),
            'matterCategories' => MatterCategory::where('is_active', true)->orWhere('id', $matter->matter_category_id)->orderBy('sort_order')->orderBy('name')->get(),
            'shelves' => Shelf::where('is_active', true)->orWhere('id', $matter->shelf_id)->orderBy('sort_order')->orderBy('name')->get(),
            'opposingCounsels' => OpposingCounsel::where('is_active', true)->orWhere('id', $matter->opposing_counsel_id)->orderBy('sort_order')->orderBy('name')->get(),
            'users' => $this->assignableUsers()->orderBy('name')->get(),
            'statuses' => Matter::STATUSES,
        ]);
    }

    public function update(Request $request, Matter $matter)
    {
        abort_unless(Matter::visibleTo($request->user())->whereKey($matter->id)->exists(), 404);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:191'],
            'ultimate_client_id' => ['nullable', 'exists:clients,id'],
            'practice_area_id' => ['required', 'exists:practice_areas,id'],
            'instruction_type_id' => ['nullable', 'exists:instruction_types,id'],
            'business_industry_id' => ['nullable', 'exists:business_industries,id'],
            'matter_category_id' => ['nullable', 'exists:matter_categories,id'],
            'shelf_id' => ['nullable', 'exists:shelves,id'],
            'opened_on' => ['required', 'date'],
            'instruction_date' => ['nullable', 'date', 'before_or_equal:opened_on'],
            'closed_on' => ['nullable', 'date', 'after_or_equal:opened_on'],
            'status' => ['required', Rule::in(array_keys(Matter::STATUSES))],
            'privacy_status' => ['required', Rule::in(['public', 'private'])],
            'opposite_counsel' => ['nullable', 'string', 'max:191'],
            'opposing_counsel_id' => ['nullable', 'exists:opposing_counsels,id'],
            'description' => ['required', 'string', 'max:3000'],
            'next_action' => ['nullable', 'string', 'max:255'],
            'next_action_due' => ['nullable', 'date'],
            'partner_ids' => ['required', 'array', 'min:1'],
            'partner_ids.*' => ['distinct', 'exists:users,id'],
            'associate_ids' => ['nullable', 'array'],
            'associate_ids.*' => ['distinct', 'exists:users,id'],
        ]);

        $this->validateUltimateClient($data, $request->user());
        $this->validateAssignableUsers($data);

        DB::transaction(function () use ($matter, $data) {
            $partnerIds = array_values(array_unique($data['partner_ids']));
            $associateIds = array_values(array_diff(array_unique($data['associate_ids'] ?? []), $partnerIds));
            $matter->update(collect($data)->except(['partner_ids', 'associate_ids'])->toArray());

            $matter->assignments()->whereNotIn('user_id', array_merge($partnerIds, $associateIds))->delete();
            foreach (['partner' => $partnerIds, 'associate' => $associateIds] as $role => $userIds) {
                foreach ($userIds as $index => $userId) {
                    $matter->assignments()->updateOrCreate(
                        ['user_id' => $userId],
                        ['assignment_role' => $role, 'assigned_on' => now()->toDateString(), 'is_lead' => $role === 'partner' && $index === 0]
                    );
                }
            }
        });

        return redirect()->route('matters.show', $matter)->with('status', 'Matter updated.');
    }

    public function storeForClient(Request $request, Client $client)
    {
        abort_unless(Client::forBranchOf($request->user())->whereKey($client->id)->exists(), 404);

        abort_if((bool) $client->matter, 422, 'This client already has a matter.');
        abort_if($client->files()->doesntExist(), 422, 'Open a file for this client before opening a matter.');

        $data = $request->validate([
            'title' => ['required', 'string', 'max:191'],
            'practice_area_id' => ['required', 'exists:practice_areas,id'],
            'instruction_type_id' => ['nullable', 'exists:instruction_types,id'],
            'business_industry_id' => ['nullable', 'exists:business_industries,id'],
            'matter_category_id' => ['nullable', 'exists:matter_categories,id'],
            'shelf_id' => ['nullable', 'exists:shelves,id'],
            'opened_on' => ['required', 'date'],
            'instruction_date' => ['nullable', 'date', 'before_or_equal:opened_on'],
            'privacy_status' => ['required', Rule::in(['public', 'private'])],
            'opposite_counsel' => ['nullable', 'string', 'max:191'],
            'opposing_counsel_id' => ['nullable', 'exists:opposing_counsels,id'],
            'description' => ['required', 'string', 'max:3000'],
            'next_action' => ['nullable', 'string', 'max:255'],
            'next_action_due' => ['nullable', 'date'],
            'partner_ids' => ['required', 'array', 'min:1'],
            'partner_ids.*' => ['distinct', 'exists:users,id'],
            'associate_ids' => ['nullable', 'array'],
            'associate_ids.*' => ['distinct', 'exists:users,id'],
        ]);

        $this->validateAssignableUsers($data);

        $matter = DB::transaction(function () use ($client, $data) {
            $partnerIds = $data['partner_ids'];
            $associateIds = $data['associate_ids'] ?? [];

            $matter = Matter::create(collect($data)
                ->except(['partner_ids', 'associate_ids'])
                ->merge([
                    'client_id' => $client->id,
                    'opened_by' => auth()->id(),
                    'branch_id' => $client->branch_id ?? auth()->user()->branch_id,
                    'department_id' => auth()->user()->department_id,
                    'reference_no' => MonthlyReferenceNumber::make(Matter::class, 'reference_no', 'MT'),
                    'status' => 'open',
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

            $client->files()->whereNull('matter_id')->update(['matter_id' => $matter->id]);

            return $matter;
        });

        return redirect()
            ->route('matters.show', $matter)
            ->with('status', 'Matter '.$matter->reference_no.' opened. All files for this client are now under it.');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:191'],
            'client_id' => [
                'required',
                'exists:clients,id',
                function ($attribute, $value, $fail) {
                    if (Matter::where('client_id', $value)->exists()) {
                        $fail('This client already has a matter. Add files to the existing matter instead.');
                    }
                },
            ],
            'ultimate_client_id' => ['nullable', 'exists:clients,id'],
            'practice_area_id' => ['required', 'exists:practice_areas,id'],
            'instruction_type_id' => ['nullable', 'exists:instruction_types,id'],
            'business_industry_id' => ['nullable', 'exists:business_industries,id'],
            'matter_category_id' => ['nullable', 'exists:matter_categories,id'],
            'shelf_id' => ['nullable', 'exists:shelves,id'],
            'opened_on' => ['required', 'date'],
            'instruction_date' => ['nullable', 'date', 'before_or_equal:opened_on'],
            'closed_on' => ['nullable', 'date', 'after_or_equal:opened_on'],
            'status' => ['required', Rule::in(array_keys(Matter::STATUSES))],
            'privacy_status' => ['required', Rule::in(['public', 'private'])],
            'opposite_counsel' => ['nullable', 'string', 'max:191'],
            'opposing_counsel_id' => ['nullable', 'exists:opposing_counsels,id'],
            'description' => ['required', 'string', 'max:3000'],
            'next_action' => ['nullable', 'string', 'max:255'],
            'next_action_due' => ['nullable', 'date'],
            'partner_ids' => ['required', 'array', 'min:1'],
            'partner_ids.*' => ['distinct', 'exists:users,id'],
            'associate_ids' => ['nullable', 'array'],
            'associate_ids.*' => ['distinct', 'exists:users,id'],
        ]);

        $client = Client::forBranchOf($request->user())->find($data['client_id']);
        abort_unless($client, 422);
        $this->validateUltimateClient($data, $request->user());
        $this->validateAssignableUsers($data);

        DB::transaction(function () use ($data, $client) {
            $partnerIds = $data['partner_ids'];
            $associateIds = $data['associate_ids'] ?? [];

            $matter = Matter::create(collect($data)
                ->except(['partner_ids', 'associate_ids'])
                ->merge([
                    'opened_by' => auth()->id(),
                    'branch_id' => $client->branch_id ?? auth()->user()->branch_id,
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

    private function assignableUsers()
    {
        return User::query()
            ->where(fn ($query) => $query->whereNull('account_type')->orWhere('account_type', 'staff'))
            ->whereHas('staffProfile', fn ($query) => $query->where('employment_status', 'active'));
    }

    private function validateAssignableUsers(array $data): void
    {
        $ids = array_unique(array_merge($data['partner_ids'], $data['associate_ids'] ?? []));
        if ($this->assignableUsers()->whereIn('id', $ids)->count() !== count($ids)) {
            throw ValidationException::withMessages(['partner_ids' => 'Select active internal staff for the responsible team.']);
        }
    }

    private function validateUltimateClient(array $data, User $user): void
    {
        if (! empty($data['ultimate_client_id']) && ! Client::forBranchOf($user)->whereKey($data['ultimate_client_id'])->exists()) {
            throw ValidationException::withMessages(['ultimate_client_id' => 'Select a client in your branch.']);
        }
    }

}
