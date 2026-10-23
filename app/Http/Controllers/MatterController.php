<?php

namespace App\Http\Controllers;

use App\Exports\MattersExport;
use App\Imports\MattersImport;
use App\Models\AuditLog;
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
use App\Support\MonthlyReferenceNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
                'label' => 'Planning',
                'value' => (clone $baseQuery)->where('status', 'planning')->count(),
                'icon' => 'mdi-clipboard-list-outline',
                'route' => route('matters.index', ['status' => 'planning']),
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
                    ? ['open', 'planning']
                    : ['active', 'waiting_for_client', 'waiting_for_third_party_or_court'];

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
            'clients' => $this->matterEligibleClients(request()->user())->orderBy('name')->get(),
            'practiceAreas' => PracticeArea::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'instructionTypes' => InstructionType::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'businessIndustries' => BusinessIndustry::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'matterCategories' => MatterCategory::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'shelves' => Shelf::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'opposingCounsels' => OpposingCounsel::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'users' => $this->assignableUsers()->with('roles')->orderBy('name')->get(),
            'availableFiles' => File::whereNull('matter_id')
                ->whereHas('client', fn ($query) => $query->forBranchOf(request()->user()))
                ->with('client')
                ->orderBy('file_name')
                ->get(),
        ]);
    }

    public function createForClient(Client $client)
    {
        abort_unless($this->matterEligibleClients(request()->user())->whereKey($client->id)->exists(), 404);

        return view('modules.matters.open', [
            'client' => $client,
            'matterNumber' => MonthlyReferenceNumber::make(Matter::class, 'reference_no', 'MT'),
            'practiceAreas' => PracticeArea::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'instructionTypes' => InstructionType::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'businessIndustries' => BusinessIndustry::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'matterCategories' => MatterCategory::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'shelves' => Shelf::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'opposingCounsels' => OpposingCounsel::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'users' => $this->assignableUsers()->with('roles')->orderBy('name')->get(),
            'availableFiles' => $client->files()->whereNull('matter_id')->orderBy('file_name')->get(),
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
            'users' => $this->assignableUsers()->with('roles')->orderBy('name')->get(),
            'availableFiles' => File::where('client_id', $matter->client_id)
                ->where(fn ($query) => $query->whereNull('matter_id')->orWhere('matter_id', $matter->id))
                ->orderBy('file_name')
                ->get(),
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
            'closed_on' => ['nullable', 'required_if:status,closed,archived', 'date', 'after_or_equal:opened_on', 'before_or_equal:today'],
            'status' => ['required', Rule::in(array_keys(Matter::STATUSES))],
            'privacy_status' => ['required', Rule::in(['public', 'private'])],
            'opposite_counsel' => ['nullable', 'string', 'max:191'],
            'opposing_counsel_id' => ['nullable', 'exists:opposing_counsels,id'],
            'description' => ['required', 'string', 'max:3000'],
            'client_summary' => ['nullable', 'string', 'max:3000'],
            'next_action' => ['nullable', 'string', 'max:255'],
            'next_action_due' => ['nullable', 'date'],
            'file_ids' => ['sometimes', 'nullable', 'array'],
            'file_ids.*' => ['integer', 'distinct', 'exists:files,id'],
            'partner_ids' => ['required', 'array', 'min:1'],
            'partner_ids.*' => ['distinct', 'exists:users,id'],
            'associate_ids' => ['nullable', 'array'],
            'associate_ids.*' => ['distinct', 'exists:users,id'],
        ]);

        if ($data['status'] === 'archived' && ! in_array($matter->status, ['closed', 'archived'], true)) {
            throw ValidationException::withMessages([
                'status' => 'Close the matter before archiving it.',
            ]);
        }

        if (! in_array($data['status'], ['closed', 'archived'], true)) {
            $data['closed_on'] = null;
        }

        $this->validateUltimateClient($data, $request->user());
        $this->validateAssignableUsers($data);
        $this->validateSelectedFiles($data['file_ids'] ?? null, $matter->client_id, $matter->id, $request->user());

        DB::transaction(function () use ($matter, $data) {
            $partnerIds = array_values(array_unique($data['partner_ids']));
            $associateIds = array_values(array_diff(array_unique($data['associate_ids'] ?? []), $partnerIds));
            $matter->update(collect($data)->except(['partner_ids', 'associate_ids', 'file_ids'])->toArray());

            $matter->assignments()
                ->whereNotIn('user_id', array_merge($partnerIds, $associateIds))
                ->get()
                ->each
                ->delete();
            foreach (['partner' => $partnerIds, 'associate' => $associateIds] as $role => $userIds) {
                foreach ($userIds as $index => $userId) {
                    $matter->assignments()->updateOrCreate(
                        ['user_id' => $userId],
                        ['assignment_role' => $role, 'assigned_on' => now()->toDateString(), 'is_lead' => $role === 'partner' && $index === 0]
                    );
                }
            }

            if (array_key_exists('file_ids', $data)) {
                $fileIds = array_values(array_unique($data['file_ids'] ?? []));
                $matter->files()
                    ->whereNotIn('id', $fileIds)
                    ->get()
                    ->each(fn (File $file) => $file->update(['matter_id' => null]));
                File::whereIn('id', $fileIds)->get()->each(fn (File $file) => $file->update(['matter_id' => $matter->id]));
            }
        });

        return redirect()->route('matters.show', $matter)->with('status', 'Matter updated.');
    }

    public function storeForClient(Request $request, Client $client)
    {
        abort_unless($this->matterEligibleClients($request->user())->whereKey($client->id)->exists(), 404);

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
            'client_summary' => ['nullable', 'string', 'max:3000'],
            'next_action' => ['nullable', 'string', 'max:255'],
            'next_action_due' => ['nullable', 'date'],
            'file_ids' => ['nullable', 'array'],
            'file_ids.*' => ['integer', 'distinct', 'exists:files,id'],
            'partner_ids' => ['required', 'array', 'min:1'],
            'partner_ids.*' => ['distinct', 'exists:users,id'],
            'associate_ids' => ['nullable', 'array'],
            'associate_ids.*' => ['distinct', 'exists:users,id'],
            'documents' => ['nullable', 'array', 'max:10'],
            'documents.*' => ['file', 'max:20480', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx'],
        ]);

        $this->validateAssignableUsers($data);
        $this->validateSelectedFiles($data['file_ids'] ?? [], $client->id, null, $request->user());

        $matter = DB::transaction(function () use ($client, $data, $request) {
            $partnerIds = $data['partner_ids'];
            $associateIds = $data['associate_ids'] ?? [];

            $matter = Matter::create(collect($data)
                ->except(['partner_ids', 'associate_ids', 'file_ids', 'documents'])
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

            $this->linkSelectedFiles($matter, $data['file_ids'] ?? []);
            $this->storeMatterDocuments($matter, $request);

            return $matter;
        });

        $redirect = $request->input('_client_profile_modal') === 'matter'
            ? redirect()->route('clients.show', $client)
            : redirect()->route('matters.show', $matter);

        return $redirect->with('status', 'Matter '.$matter->reference_no.' opened with the selected files.');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:191'],
            'client_id' => [
                'required',
                'exists:clients,id',
            ],
            'ultimate_client_id' => ['nullable', 'exists:clients,id'],
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
            'client_summary' => ['nullable', 'string', 'max:3000'],
            'next_action' => ['nullable', 'string', 'max:255'],
            'next_action_due' => ['nullable', 'date'],
            'file_ids' => ['nullable', 'array'],
            'file_ids.*' => ['integer', 'distinct', 'exists:files,id'],
            'partner_ids' => ['required', 'array', 'min:1'],
            'partner_ids.*' => ['distinct', 'exists:users,id'],
            'associate_ids' => ['nullable', 'array'],
            'associate_ids.*' => ['distinct', 'exists:users,id'],
            'documents' => ['nullable', 'array', 'max:10'],
            'documents.*' => ['file', 'max:20480', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx'],
        ]);

        $client = $this->matterEligibleClients($request->user())->find($data['client_id']);
        if (! $client) {
            throw ValidationException::withMessages([
                'client_id' => 'Select an active, approved client. Prospects must complete intake before a matter can be opened.',
            ]);
        }
        $this->validateUltimateClient($data, $request->user());
        $this->validateAssignableUsers($data);
        $this->validateSelectedFiles($data['file_ids'] ?? [], $client->id, null, $request->user());

        DB::transaction(function () use ($data, $client, $request) {
            $partnerIds = $data['partner_ids'];
            $associateIds = $data['associate_ids'] ?? [];

            $matter = Matter::create(collect($data)
                ->except(['partner_ids', 'associate_ids', 'file_ids', 'documents'])
                ->merge([
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

            $this->linkSelectedFiles($matter, $data['file_ids'] ?? []);
            $this->storeMatterDocuments($matter, $request);
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

    private function matterEligibleClients(User $user)
    {
        return Client::forBranchOf($user)
            ->where('status', 'active')
            ->where('is_prospect', false);
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

    private function validateSelectedFiles(?array $fileIds, int $clientId, ?int $matterId, User $user): void
    {
        $fileIds = array_values(array_unique($fileIds ?? []));

        if ($fileIds === []) {
            return;
        }

        $count = File::query()
            ->whereIn('id', $fileIds)
            ->where('client_id', $clientId)
            ->where(function ($query) use ($matterId) {
                $query->whereNull('matter_id');
                if ($matterId) {
                    $query->orWhere('matter_id', $matterId);
                }
            })
            ->whereHas('client', fn ($query) => $query->forBranchOf($user))
            ->count();

        if ($count !== count($fileIds)) {
            throw ValidationException::withMessages([
                'file_ids' => 'Select only unlinked files belonging to this client.',
            ]);
        }
    }

    private function linkSelectedFiles(Matter $matter, array $fileIds): void
    {
        $fileIds = array_values(array_unique($fileIds));
        if ($fileIds !== []) {
            File::whereIn('id', $fileIds)
                ->where('client_id', $matter->client_id)
                ->get()
                ->each(fn (File $file) => $file->update(['matter_id' => $matter->id]));
        }
    }

    private function storeMatterDocuments(Matter $matter, Request $request): void
    {
        foreach ($request->file('documents', []) as $document) {
            $documentKey = Str::slug(pathinfo($document->getClientOriginalName(), PATHINFO_FILENAME));
            $documentKey = $documentKey !== '' ? $documentKey : 'matter-document';
            $version = ((int) $matter->attachments()->where('document_key', $documentKey)->max('version_number')) + 1;
            $previousVersions = $matter->attachments()
                ->where('document_key', $documentKey)
                ->where('is_current', true)
                ->get();
            $previousVersions->each(fn ($attachment) => $attachment->update(['is_current' => false]));

            $attachment = $matter->addAttachment($document, [
                'category' => 'matter-document',
                'title' => $document->getClientOriginalName(),
                'document_key' => $documentKey,
                'version_number' => $version,
                'is_current' => true,
                'is_client_visible' => false,
            ]);

            AuditLog::recordFor($attachment, 'uploaded', [], [
                'version_number' => $version,
                'document_key' => $documentKey,
                'source' => 'matter_creation',
            ]);
            $previousVersions->each(fn ($previous) => AuditLog::recordFor(
                $previous,
                'superseded',
                ['is_current' => true],
                ['is_current' => false, 'replaced_by' => $attachment->id]
            ));
        }
    }
}
