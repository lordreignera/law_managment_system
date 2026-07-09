<?php

namespace App\Http\Controllers;

use App\Exports\RecoveryAccountsExport;
use App\Imports\RecoveryAccountsImport;
use App\Models\Branch;
use App\Models\RecoveryAccount;
use App\Models\RecoveryActivity;
use App\Models\RecoveryClient;
use App\Models\RecoveryImportBatch;
use App\Models\User;
use App\Support\Recoveries\RecoveryPortfolioMapper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class RecoveryController extends Controller
{
    public function dashboard(Request $request)
    {
        $base = RecoveryAccount::query()->forBranchOf($request->user());

        $clientRows = RecoveryClient::withCount('accounts')
            ->withSum('accounts as outstanding_total', 'outstanding_amount')
            ->withSum('accounts as recovered_total', 'amount_recovered')
            ->orderBy('name')
            ->get();

        $officerRows = User::role('Recovery Officer')
            ->withCount(['assignedRecoveries as active_recoveries_count' => fn (Builder $query) => $query->where('status', 'active')])
            ->withSum('assignedRecoveries as outstanding_total', 'outstanding_amount')
            ->withSum('assignedRecoveries as recovered_total', 'amount_recovered')
            ->orderBy('name')
            ->get();

        return view('modules.recoveries.dashboard', [
            'summary' => [
                'active' => (clone $base)->where('status', 'active')->count(),
                'unassigned' => (clone $base)->whereNull('assigned_to')->count(),
                'outstanding' => (clone $base)->sum('outstanding_amount'),
                'recovered' => (clone $base)->sum('amount_recovered'),
            ],
            'recentBatches' => RecoveryImportBatch::with(['client', 'uploader'])->latest()->take(6)->get(),
            'clientRows' => $clientRows,
            'officerRows' => $officerRows,
        ]);
    }

    public function index(Request $request)
    {
        $accounts = $this->filteredAccountsQuery($request)
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('modules.recoveries.index', [
            'accounts' => $accounts,
            'clients' => RecoveryClient::orderBy('name')->get(['id', 'name']),
            'officers' => $this->recoveryOfficers(),
            'portfolioTypes' => $this->portfolioTypes(),
            'filters' => $request->only(['search', 'status', 'client', 'officer', 'portfolio_type']),
        ]);
    }

    /**
     * The signed-in officer's own assigned recoveries.
     */
    public function mine(Request $request)
    {
        $user = $request->user();

        $accounts = RecoveryAccount::with(['client', 'branch'])
            ->where('assigned_to', $user->id)
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search) {
                    $query
                        ->where('debtor_name', 'like', "%{$search}%")
                        ->orWhere('account_number', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $base = RecoveryAccount::where('assigned_to', $user->id);

        $summary = [
            'total' => (clone $base)->count(),
            'active' => (clone $base)->where('status', 'active')->count(),
            'outstanding' => (clone $base)->sum('outstanding_amount'),
            'recovered' => (clone $base)->sum('amount_recovered'),
        ];

        return view('modules.recoveries.mine', [
            'accounts' => $accounts,
            'summary' => $summary,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function create(Request $request)
    {
        return view('modules.recoveries.create', [
            'clients' => RecoveryClient::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'branches' => Branch::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'officers' => $this->recoveryOfficers(),
            'statuses' => RecoveryAccount::STATUSES,
        ]);
    }

    public function importForm()
    {
        return view('modules.recoveries.import', [
            'clients' => RecoveryClient::where('is_active', true)->orderBy('name')->get(),
            'officers' => $this->recoveryOfficers(),
            'defaultPortfolioTypes' => RecoveryPortfolioMapper::DEFAULT_PORTFOLIOS,
        ]);
    }

    public function importStore(Request $request)
    {
        $data = $request->validate([
            'recovery_client_id' => ['required', 'exists:recovery_clients,id'],
            'portfolio_type' => ['required', 'string', 'max:100'],
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:20480'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'match_collector' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $batch = RecoveryImportBatch::create([
            'recovery_client_id' => $data['recovery_client_id'],
            'uploaded_by' => $request->user()->id,
            'source_file' => $request->file('file')->getClientOriginalName(),
            'portfolio_type' => $data['portfolio_type'],
            'status' => 'importing',
            'notes' => $data['notes'] ?? null,
        ]);

        $import = new RecoveryAccountsImport(
            $batch,
            $request->user(),
            $data['assigned_to'] ?? null,
            $request->boolean('match_collector'),
        );

        Excel::import($import, $request->file('file'));

        return redirect()
            ->route('recoveries.batches.show', $batch)
            ->with('status', "Imported {$import->imported} recovery account(s); skipped {$import->skipped}.");
    }

    public function storeClient(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:191', Rule::unique('recovery_clients', 'name')],
            'contact_person' => ['nullable', 'string', 'max:191'],
            'email' => ['nullable', 'email', 'max:191'],
            'phone' => ['nullable', 'string', 'max:50'],
            'portfolio_types' => ['nullable', 'string', 'max:3000'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $data['portfolio_types'] = collect(preg_split('/\r\n|\r|\n/', (string) ($data['portfolio_types'] ?? '')))
            ->map(fn ($type) => trim($type))
            ->filter()
            ->values()
            ->all();
        $data['is_active'] = true;

        RecoveryClient::create($data);

        return back()->with('status', 'Recovery client added.');
    }

    public function batchShow(RecoveryImportBatch $batch)
    {
        return view('modules.recoveries.batch', [
            'batch' => $batch->load(['client', 'uploader']),
            'accounts' => $batch->accounts()->with(['assignee'])->latest()->paginate(15),
            'officers' => $this->recoveryOfficers(),
        ]);
    }

    public function assignBatch(Request $request, RecoveryImportBatch $batch)
    {
        $data = $request->validate([
            'assigned_to' => ['required', 'exists:users,id'],
            'scope' => ['required', 'in:unassigned,all'],
        ]);

        $officer = User::findOrFail($data['assigned_to']);
        $query = $batch->accounts();

        if ($data['scope'] === 'unassigned') {
            $query->whereNull('assigned_to');
        }

        $updated = $query->update([
            'assigned_to' => $officer->id,
            'assigned_by' => $request->user()->id,
            'assigned_at' => now(),
            'branch_id' => $officer->branch_id,
        ]);

        $batch->update([
            'assigned_count' => $batch->accounts()->whereNotNull('assigned_to')->count(),
            'status' => 'assigned',
        ]);

        return back()->with('status', "{$updated} recovery account(s) assigned to {$officer->name}.");
    }

    public function exportAccounts(Request $request)
    {
        return Excel::download(
            new RecoveryAccountsExport($request->user(), $request->only(['search', 'status', 'client', 'officer', 'portfolio_type'])),
            'recovery-accounts-'.now()->format('Ymd-His').'.xlsx'
        );
    }

    public function store(Request $request)
    {
        $data = $this->validateAccount($request);

        $data = $this->applyAssignment($data, $request);
        $data['amount_recovered'] = 0;

        $account = RecoveryAccount::create($data);

        return redirect()
            ->route('recoveries.show', $account)
            ->with('status', 'Recovery account created.');
    }

    public function show(Request $request, RecoveryAccount $recovery)
    {
        return view('modules.recoveries.show', [
            'account' => $recovery->load(['client', 'branch', 'assignee', 'assigner', 'importBatch', 'activities.user']),
            'activityTypes' => RecoveryActivity::TYPES,
            'canManage' => $request->user()->can('recoveries.update'),
        ]);
    }

    public function edit(RecoveryAccount $recovery)
    {
        return view('modules.recoveries.edit', [
            'account' => $recovery,
            'clients' => RecoveryClient::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'branches' => Branch::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'officers' => $this->recoveryOfficers(),
            'statuses' => RecoveryAccount::STATUSES,
        ]);
    }

    public function update(Request $request, RecoveryAccount $recovery)
    {
        $data = $this->validateAccount($request, true);

        $data = $this->applyAssignment($data, $request, $recovery);

        $recovery->update($data);

        return redirect()
            ->route('recoveries.show', $recovery)
            ->with('status', 'Recovery account updated.');
    }

    public function destroy(RecoveryAccount $recovery)
    {
        $recovery->delete();

        return redirect()
            ->route('recoveries.index')
            ->with('status', 'Recovery account removed.');
    }

    /**
     * Users who hold the Recovery Officer role, eligible for assignment.
     */
    private function recoveryOfficers()
    {
        return User::role('Recovery Officer')
            ->with('branch')
            ->orderBy('name')
            ->get(['id', 'name', 'branch_id']);
    }

    /**
     * Stamp the branch + assignment metadata from the chosen officer.
     */
    private function applyAssignment(array $data, Request $request, ?RecoveryAccount $existing = null): array
    {
        $assignedTo = $data['assigned_to'] ?? null;
        $previousAssignee = $existing?->assigned_to;

        if ($assignedTo && $assignedTo != $previousAssignee) {
            $data['assigned_by'] = $request->user()->id;
            $data['assigned_at'] = now();
        }

        // Default the branch from the assigned officer when not explicitly set.
        if (empty($data['branch_id']) && $assignedTo) {
            $data['branch_id'] = User::find($assignedTo)?->branch_id;
        }

        return $data;
    }

    private function validateAccount(Request $request, bool $isUpdate = false): array
    {
        return $request->validate([
            'recovery_client_id' => ['required', 'exists:recovery_clients,id'],
            'debtor_name' => ['required', 'string', 'max:191'],
            'account_number' => ['nullable', 'string', 'max:100'],
            'customer_number' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:191'],
            'employer' => ['nullable', 'string', 'max:191'],
            'branch_name' => ['nullable', 'string', 'max:191'],
            'region' => ['nullable', 'string', 'max:191'],
            'portfolio_type' => ['nullable', 'string', 'max:100'],
            'collector_name' => ['nullable', 'string', 'max:191'],
            'operative_account' => ['nullable', 'string', 'max:100'],
            'days_past_due' => ['nullable', 'integer', 'min:0'],
            'principal_amount' => ['nullable', 'numeric', 'min:0'],
            'interest_amount' => ['nullable', 'numeric', 'min:0'],
            'arrears_amount' => ['nullable', 'numeric', 'min:0'],
            'outstanding_amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'bucket' => ['nullable', 'string', 'max:100'],
            'collateral_held' => ['nullable', 'string', 'max:2000'],
            'cause_of_default' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', 'in:'.implode(',', array_keys(RecoveryAccount::STATUSES))],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'branch_id' => ['nullable', 'exists:branches,id'],
        ]);
    }

    private function filteredAccountsQuery(Request $request): Builder
    {
        return RecoveryAccount::with(['client', 'assignee', 'importBatch'])
            ->forBranchOf($request->user())
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search) {
                    $query
                        ->where('debtor_name', 'like', "%{$search}%")
                        ->orWhere('account_number', 'like', "%{$search}%")
                        ->orWhere('customer_number', 'like', "%{$search}%")
                        ->orWhere('collector_name', 'like', "%{$search}%")
                        ->orWhereHas('client', fn ($query) => $query->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('client'), fn ($query) => $query->where('recovery_client_id', $request->integer('client')))
            ->when($request->filled('officer'), fn ($query) => $query->where('assigned_to', $request->integer('officer')))
            ->when($request->filled('portfolio_type'), fn ($query) => $query->where('portfolio_type', $request->string('portfolio_type')->toString()));
    }

    private function portfolioTypes(): array
    {
        return RecoveryClient::query()
            ->get(['portfolio_types'])
            ->flatMap(fn (RecoveryClient $client) => $client->portfolio_types ?: [])
            ->merge(RecoveryPortfolioMapper::DEFAULT_PORTFOLIOS)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }
}
