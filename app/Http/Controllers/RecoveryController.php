<?php

namespace App\Http\Controllers;

use App\Exports\RecoveryAccountsExport;
use App\Exports\RecoveryReportExport;
use App\Imports\RecoveryAccountsImport;
use App\Models\Branch;
use App\Models\RecoveryAccount;
use App\Models\RecoveryActivity;
use App\Models\RecoveryClient;
use App\Models\RecoveryImportBatch;
use App\Models\RecoveryImportTemplate;
use App\Models\RecoverySkipTraceCheck;
use App\Models\User;
use App\Support\Recoveries\RecoveryPromiseReminderService;
use App\Support\Recoveries\RecoveryPortfolioProfiles;
use App\Support\Recoveries\RecoveryAccountIdentity;
use App\Support\Recoveries\RecoveryReferenceData;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;

class RecoveryController extends Controller
{
    public function dashboard(Request $request)
    {
        $base = RecoveryAccount::query()->forBranchOf($request->user());

        $clientRows = RecoveryClient::withCount(['accounts' => fn (Builder $query) => $query->forBranchOf($request->user())])
            ->withSum(['accounts as outstanding_total' => fn (Builder $query) => $query->forBranchOf($request->user())->whereNull('settled_externally_at')], 'outstanding_amount')
            ->withSum(['accounts as recovered_for_outstanding' => fn (Builder $query) => $query->forBranchOf($request->user())->whereNull('settled_externally_at')], 'amount_recovered')
            ->withSum(['accounts as recovered_total' => fn (Builder $query) => $query->forBranchOf($request->user())], 'amount_recovered')
            ->orderBy('name')
            ->get();

        $officerRows = User::whereHas('roles', fn ($query) => $query->where('name', 'Recovery Officer'))
            ->withCount(['assignedRecoveries as active_recoveries_count' => fn (Builder $query) => $query->forBranchOf($request->user())->where('status', 'active')])
            ->withSum(['assignedRecoveries as outstanding_total' => fn (Builder $query) => $query->forBranchOf($request->user())->whereNull('settled_externally_at')], 'outstanding_amount')
            ->withSum(['assignedRecoveries as recovered_for_outstanding' => fn (Builder $query) => $query->forBranchOf($request->user())->whereNull('settled_externally_at')], 'amount_recovered')
            ->withSum(['assignedRecoveries as recovered_total' => fn (Builder $query) => $query->forBranchOf($request->user())], 'amount_recovered')
            ->orderBy('name')
            ->get();

        $unsettled = (clone $base)->whereNull('settled_externally_at');
        $pipelineRows = (clone $base)->get()->groupBy('pipeline_stage')->map(fn ($group, $stage) => [
            'stage' => $stage,
            'label' => RecoveryAccount::PIPELINE_STAGES[$stage] ?? str($stage)->headline()->toString(),
            'accounts' => $group->count(),
            'outstanding' => (float) $group->sum(fn (RecoveryAccount $account) => $account->net_outstanding_balance),
            'priority' => $group->whereIn('priority', ['high', 'urgent'])->count(),
        ])->sortByDesc('outstanding')->values();

        return view('modules.recoveries.dashboard', [
            'summary' => [
                'active' => (clone $base)->where('status', 'active')->count(),
                'unassigned' => (clone $base)->whereNull('assigned_to')->count(),
                'original_outstanding' => (clone $unsettled)->sum('outstanding_amount'),
                'outstanding' => max((clone $unsettled)->sum('outstanding_amount') - (clone $unsettled)->sum('amount_recovered'), 0),
                'recovered' => (clone $base)->sum('amount_recovered'),
            ],
            'recentBatches' => RecoveryImportBatch::with(['client', 'uploader'])
                ->whereHas('accounts', fn (Builder $query) => $query->forBranchOf($request->user()))
                ->latest()
                ->take(6)
                ->get(),
            'clientRows' => $clientRows,
            'officerRows' => $officerRows,
            'pipelineRows' => $pipelineRows,
        ]);
    }

    public function sendPromiseReminders(Request $request, RecoveryPromiseReminderService $reminders)
    {
        $result = $reminders->sendDue(now()->startOfDay(), $request->user());

        return back()->with('status', sprintf(
            'Promise reminders processed: %d sent, %d skipped, %d failed.',
            $result['sent'],
            $result['skipped'],
            $result['failed'],
        ));
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
            'pipelineStages' => RecoveryAccount::PIPELINE_STAGES,
            'filters' => $request->only(['search', 'status', 'client', 'officer', 'portfolio_type', 'pipeline_stage']),
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
        $unsettled = (clone $base)->whereNull('settled_externally_at');

        $summary = [
            'total' => (clone $base)->count(),
            'active' => (clone $base)->where('status', 'active')->count(),
            'original_outstanding' => (clone $unsettled)->sum('outstanding_amount'),
            'outstanding' => max((clone $unsettled)->sum('outstanding_amount') - (clone $unsettled)->sum('amount_recovered'), 0),
            'recovered' => (clone $base)->sum('amount_recovered'),
        ];

        $reportFilters = $this->mineReportFilters($request);
        $collectionReports = $this->mineCollectionSummary($user);
        $filteredCollections = $this->mineCollectionQuery($user, $reportFilters)
            ->with(['account.client'])
            ->latest('activity_at')
            ->get();

        return view('modules.recoveries.mine', [
            'accounts' => $accounts,
            'summary' => $summary,
            'collectionReports' => $collectionReports,
            'filteredCollections' => $filteredCollections,
            'collectionChart' => $this->mineCollectionChart($filteredCollections, $reportFilters['grain']),
            'bankChart' => $this->mineBankChart($filteredCollections),
            'reportFilters' => $reportFilters,
            'clients' => RecoveryClient::where('is_active', true)->orderBy('name')->get(),
            'filters' => $request->only(['search', 'status']),
            'activityTypes' => RecoveryActivity::TYPES,
        ]);
    }

    public function exportMineReport(Request $request)
    {
        $filters = $this->mineReportFilters($request);
        $collections = $this->mineCollectionQuery($request->user(), $filters)
            ->with(['account.client'])
            ->orderByDesc('activity_at')
            ->get();

        $headings = ['Date', 'Debtor', 'Bank / Client', 'Account', 'Amount Paid', 'Balance After', 'Response Notes'];
        $rows = $collections->map(fn (RecoveryActivity $activity) => [
            $activity->activity_at?->format('d M Y, H:i'),
            $activity->account?->debtor_name ?: '-',
            $activity->account?->client?->name ?: '-',
            $activity->account?->account_number ?: '-',
            number_format((float) $activity->amount_paid, 2),
            $activity->outstanding_balance_after !== null ? number_format((float) $activity->outstanding_balance_after, 2) : '-',
            $activity->notes,
        ])->all();

        $title = 'My Recovery Collections';
        $filename = 'my-recovery-collections-'.$filters['date_from'].'-'.$filters['date_to'];

        if ($request->string('format')->toString() === 'pdf') {
            return Pdf::loadView('modules.recoveries.exports.pdf', [
                'title' => $title,
                'headings' => $headings,
                'rows' => $rows,
                'year' => now()->year,
                'generatedAt' => now(),
            ])->setPaper('a4', 'landscape')->download($filename.'.pdf');
        }

        return Excel::download(new RecoveryReportExport($headings, $rows, $title), $filename.'.xlsx');
    }

    public function create(Request $request)
    {
        return view('modules.recoveries.create', [
            'clients' => RecoveryClient::where('is_active', true)->orderBy('name')->get(),
            'branches' => Branch::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'officers' => $this->recoveryOfficers(),
            'statuses' => RecoveryAccount::STATUSES,
        ]);
    }

    public function importForm()
    {
        $templates = RecoveryImportTemplate::with('client')->orderBy('recovery_client_id')->orderBy('portfolio_type')->get()
            ->filter(fn (RecoveryImportTemplate $template) => $template->client?->is_active
                && array_key_exists($template->portfolio_type, $template->client->portfolioOptions()))
            ->values();

        return view('modules.recoveries.import', [
            'clients' => RecoveryClient::where('is_active', true)->orderBy('name')->get(),
            'templates' => $templates,
            'templateOptions' => $templates->map(fn (RecoveryImportTemplate $template) => [
                'client_id' => $template->recovery_client_id,
                'portfolio_type' => $template->portfolio_type,
                'sheet_name' => $template->sheet_name,
                'column_map' => $template->column_map,
                'download_url' => route('recoveries.templates.download', $template),
            ])->all(),
            'officers' => $this->recoveryOfficers(),
            'defaultPortfolioTypes' => [],
            'templateFields' => RecoveryPortfolioProfiles::TEMPLATE_FIELDS,
        ]);
    }

    public function importStore(Request $request)
    {
        $data = $request->validate([
            'recovery_client_id' => ['required', Rule::exists('recovery_clients', 'id')->where('is_active', true)],
            'portfolio_type' => ['required', 'string', 'max:100', Rule::in(array_keys(RecoveryClient::find($request->integer('recovery_client_id'))?->portfolioOptions() ?? []))],
            'file' => ['required', 'file', 'extensions:xlsx,xls,csv', 'max:20480'],
            'assigned_to' => ['nullable', Rule::in(User::whereHas('roles', fn ($query) => $query->where('name', 'Recovery Officer'))->pluck('id')->all())],
            'match_collector' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
            '_preview' => ['nullable', 'boolean'],
        ]);

        $fingerprint = hash_file('sha256', $request->file('file')->getRealPath());
        $existingBatch = RecoveryImportBatch::where('recovery_client_id', $data['recovery_client_id'])
            ->where('portfolio_type', $data['portfolio_type'])
            ->where('source_sha256', $fingerprint)
            ->first();
        if ($existingBatch) {
            if ($existingBatch->status === 'preview' && $this->batchVisibleTo($existingBatch, $request->user())) {
                return redirect()->route('recoveries.batches.show', $existingBatch)
                    ->with('status', 'This file already has a preview. Review it before importing.');
            }
            throw ValidationException::withMessages(['file' => 'This exact file has already been imported for the selected client and portfolio.']);
        }

        $template = RecoveryImportTemplate::where('recovery_client_id', $data['recovery_client_id'])
            ->where('portfolio_type', $data['portfolio_type'])->first();
        [$sheetName, $sheetKey] = $this->selectedPortfolioSheet(
            $request->file('file'), $template?->sheet_name ?: $data['portfolio_type']
        );

        $storedPath = $request->file('file')->store('recovery-imports', 'local');
        $previewOnly = $request->boolean('_preview');

        [$batch, $import] = DB::transaction(function () use ($data, $request, $sheetName, $sheetKey, $template, $fingerprint, $storedPath, $previewOnly) {
            $batch = RecoveryImportBatch::create([
                'recovery_client_id' => $data['recovery_client_id'],
                'uploaded_by' => $request->user()->id,
                'source_file' => $request->file('file')->getClientOriginalName(),
                'source_path' => $storedPath,
                'source_sha256' => $fingerprint,
                'portfolio_type' => $data['portfolio_type'],
                'sheet_name' => $sheetName,
                'import_options' => [
                    'assigned_to' => $data['assigned_to'] ?? null,
                    'match_collector' => $request->boolean('match_collector'),
                    'column_map' => $template?->column_map ?? [],
                ],
                'status' => $previewOnly ? 'preview' : 'importing',
                'notes' => $data['notes'] ?? null,
            ]);

            $import = new RecoveryAccountsImport(
                $batch,
                $request->user(),
                $data['assigned_to'] ?? null,
                $request->boolean('match_collector'),
                $sheetKey,
                $template?->column_map ?? [],
                $previewOnly,
            );

            try {
                Excel::import($import, $request->file('file'));
            } catch (ValidationException $exception) {
                throw $exception;
            } catch (\Throwable) {
                throw ValidationException::withMessages([
                    'file' => 'The selected worksheet could not be imported. Check the file format and column headings.',
                ]);
            }

            return [$batch, $import];
        });

        return redirect()
            ->route('recoveries.batches.show', $batch)
            ->with('status', $previewOnly
                ? "Preview ready: {$import->imported} new account(s), {$import->duplicates} possible duplicate(s). Confirm how to handle matches."
                : "Imported {$import->imported} recovery account(s); updated {$import->updated}; skipped {$import->skipped}.")
            ->with('import_errors', $import->errors);
    }

    public function commitImport(Request $request, RecoveryImportBatch $batch)
    {
        abort_unless($this->batchVisibleTo($batch, $request->user()), 403);
        abort_unless($batch->status === 'preview', 409);
        $data = $request->validate(['duplicate_action' => ['required', 'in:skip,update']]);
        abort_unless($batch->source_path && Storage::disk('local')->exists($batch->source_path), 422);

        $options = $batch->import_options ?: [];
        $sheet = $batch->sheet_name === 'CSV' ? 0 : $batch->sheet_name;

        $import = DB::transaction(function () use ($batch, $request, $options, $sheet, $data) {
            $locked = RecoveryImportBatch::whereKey($batch->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'preview', 409);
            $locked->update(['status' => 'importing']);

            $import = new RecoveryAccountsImport(
                $locked,
                $request->user(),
                $options['assigned_to'] ?? null,
                (bool) ($options['match_collector'] ?? false),
                $sheet,
                $options['column_map'] ?? [],
                false,
                $data['duplicate_action'],
            );
            Excel::import($import, Storage::disk('local')->path($locked->source_path));

            return $import;
        });

        return redirect()->route('recoveries.batches.show', $batch)
            ->with('status', "Imported {$import->imported} new account(s); updated {$import->updated}; skipped {$import->skipped}.")
            ->with('import_errors', $import->errors);
    }

    private function selectedPortfolioSheet(UploadedFile $file, string $sheetLabel): array
    {
        if (strtolower($file->getClientOriginalExtension()) === 'csv') {
            return ['CSV', 0];
        }

        try {
            $path = $file->getRealPath();
            $sheets = IOFactory::createReaderForFile($path)->listWorksheetNames($path);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['file' => 'The workbook could not be read. Upload a valid Excel or CSV file.']);
        }

        if (count($sheets) === 1) {
            return [$sheets[0], $sheets[0]];
        }

        $sheet = RecoveryPortfolioProfiles::matchingSheet($sheetLabel, $sheets);

        if (! $sheet) {
            throw ValidationException::withMessages([
                'file' => 'This workbook has multiple sheets but none matches the selected portfolio. Choose the correct portfolio or upload a single-sheet file.',
            ]);
        }

        return [$sheet, $sheet];
    }

    public function storeClient(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:191', Rule::unique('recovery_clients', 'name')],
            'contact_person' => ['nullable', 'string', 'max:191'],
            'email' => ['nullable', 'email', 'max:191'],
            'phone' => ['nullable', 'string', 'max:50'],
            'portfolio_types' => ['required', 'string', 'max:3000'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $data['portfolio_types'] = collect(preg_split('/\r\n|\r|\n/', (string) ($data['portfolio_types'] ?? '')))
            ->map(fn ($type) => trim($type))
            ->filter()
            ->unique(fn ($type) => strtolower($type))
            ->values()
            ->all();
        if (! $data['portfolio_types']) {
            throw ValidationException::withMessages(['portfolio_types' => 'Enter at least one portfolio type.']);
        }
        if (collect($data['portfolio_types'])->contains(fn ($type) => mb_strlen($type) > 100)) {
            throw ValidationException::withMessages(['portfolio_types' => 'Each portfolio name must be 100 characters or fewer.']);
        }
        $data['is_active'] = true;

        $client = RecoveryClient::create($data);

        if ($request->input('return_to') === 'import') {
            return redirect()->route('recoveries.import', ['recovery_client_id' => $client->id])
                ->with('status', 'Recovery client added. Choose a portfolio and set up its import template.');
        }

        return back()->with('status', 'Recovery client added.');
    }

    public function storeClientPortfolio(Request $request, RecoveryClient $client)
    {
        abort_unless($client->is_active, 404);
        $data = $request->validate(['portfolio_type' => ['required', 'string', 'max:100']]);
        $portfolio = trim($data['portfolio_type']);
        if ($portfolio === '') {
            throw ValidationException::withMessages(['portfolio_type' => 'Enter a portfolio name.']);
        }
        $existing = $client->portfolio_types ?: [];
        if (collect($existing)->contains(fn ($type) => strcasecmp($type, $portfolio) === 0)) {
            throw ValidationException::withMessages(['portfolio_type' => 'This portfolio is already configured for the client.']);
        }

        $client->update(['portfolio_types' => [...$existing, $portfolio]]);

        return redirect()->route('recoveries.import', [
            'recovery_client_id' => $client->id,
            'portfolio_type' => $portfolio,
        ])->with('status', 'Portfolio added. You can now create its import template.');
    }

    public function batchShow(RecoveryImportBatch $batch)
    {
        $user = request()->user();
        abort_unless($this->batchVisibleTo($batch, $user), 403);

        $batch->load(['client', 'uploader'])
            ->loadCount(['accounts' => fn (Builder $query) => $query->forBranchOf($user)])
            ->loadCount(['accounts as assigned_accounts_count' => fn (Builder $query) => $query->forBranchOf($user)->whereNotNull('assigned_to')]);

        return view('modules.recoveries.batch', [
            'batch' => $batch,
            'accounts' => $batch->accounts()->forBranchOf($user)->with(['assignee'])->latest()->paginate(15),
            'officers' => $this->recoveryOfficers(),
        ]);
    }

    public function assignBatch(Request $request, RecoveryImportBatch $batch)
    {
        abort_unless($this->batchVisibleTo($batch, $request->user()), 403);

        $data = $request->validate([
            'assigned_to' => ['required', Rule::in(User::whereHas('roles', fn ($query) => $query->where('name', 'Recovery Officer'))->pluck('id')->all())],
            'scope' => ['required', 'in:selected,unassigned,all'],
            'account_ids' => ['required_if:scope,selected', 'array', 'min:1'],
            'account_ids.*' => ['integer', 'distinct'],
        ]);

        $officer = User::findOrFail($data['assigned_to']);
        $query = $batch->accounts()->forBranchOf($request->user());

        if ($data['scope'] === 'unassigned') {
            $query->whereNull('assigned_to');
        } elseif ($data['scope'] === 'selected') {
            $ids = $data['account_ids'];
            abort_unless((clone $query)->whereIn('id', $ids)->count() === count($ids), 403);
            $query->whereIn('id', $ids);
        }

        $updated = DB::transaction(function () use ($query, $officer, $request, $batch) {
            $updated = 0;

            $query->chunkById(200, function ($accounts) use ($officer, $request, &$updated) {
                foreach ($accounts as $account) {
                    $account->update([
                        'assigned_to' => $officer->id,
                        'assigned_by' => $request->user()->id,
                        'assigned_at' => now(),
                        'branch_id' => $officer->branch_id,
                    ]);
                    $updated++;
                }
            });

            $assignedCount = $batch->accounts()->whereNotNull('assigned_to')->count();
            $batch->update([
                'assigned_count' => $assignedCount,
                'status' => $assignedCount > 0 ? 'assigned' : 'imported',
            ]);

            return $updated;
        });

        return back()->with('status', "{$updated} recovery account(s) assigned to {$officer->name}.");
    }

    public function exportAccounts(Request $request)
    {
        return Excel::download(
            new RecoveryAccountsExport($request->user(), $request->only(['search', 'status', 'client', 'officer', 'portfolio_type', 'pipeline_stage'])),
            'recovery-accounts-'.now()->format('Ymd-His').'.xlsx'
        );
    }

    public function store(Request $request)
    {
        $data = $this->validateAccount($request);
        $identity = RecoveryAccountIdentity::fromData($data);
        if (RecoveryAccountIdentity::existing((int) $data['recovery_client_id'], (string) ($data['portfolio_type'] ?? ''), $data)) {
            throw ValidationException::withMessages(['account_number' => 'This recovery account already exists for the selected client and portfolio.']);
        }
        $data['identity_key'] = $identity['key'];
        $data['identity_source'] = $identity['source'];
        $data['identity_portfolio'] = (string) ($data['portfolio_type'] ?? '');

        $data = $this->applyAssignment($data, $request);

        $account = RecoveryAccount::create($data);

        return redirect()
            ->route('recoveries.show', $account)
            ->with('status', 'Recovery account created.');
    }

    public function show(Request $request, RecoveryAccount $recovery)
    {
        abort_unless($this->accountVisibleTo($recovery, $request->user()), 403);

        return view('modules.recoveries.show', [
            'account' => $recovery->load(['client', 'branch', 'assignee', 'assigner', 'importBatch', 'activities.user', 'activities.reviewer', 'activities.attachments', 'activities.promiseReminderLogs', 'promiseReminderLogs', 'skipTraceChecks.checker']),
            'activityTypes' => RecoveryActivity::TYPES,
            'canManage' => $request->user()->can('recoveries.update'),
        ]);
    }

    public function edit(RecoveryAccount $recovery)
    {
        abort_unless(RecoveryAccount::forBranchOf(request()->user())->whereKey($recovery->id)->exists(), 403);

        return view('modules.recoveries.edit', [
            'account' => $recovery,
            'clients' => RecoveryClient::where('is_active', true)->orderBy('name')->get(),
            'branches' => Branch::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'officers' => $this->recoveryOfficers(),
            'statuses' => RecoveryAccount::STATUSES,
        ]);
    }

    public function editAssignment(RecoveryAccount $recovery)
    {
        abort_unless(RecoveryAccount::forBranchOf(request()->user())->whereKey($recovery->id)->exists(), 403);

        return view('modules.recoveries.assignment', [
            'account' => $recovery->load(['client', 'branch', 'assignee', 'assigner']),
            'branches' => Branch::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'officers' => $this->recoveryOfficers(),
        ]);
    }

    public function update(Request $request, RecoveryAccount $recovery)
    {
        abort_unless(RecoveryAccount::forBranchOf($request->user())->whereKey($recovery->id)->exists(), 403);
        $data = $this->validateAccount($request, false, $recovery);

        $identity = RecoveryAccountIdentity::fromData($data);
        $duplicate = RecoveryAccountIdentity::existing((int) $data['recovery_client_id'], (string) ($data['portfolio_type'] ?? ''), $data);
        if ($duplicate && $duplicate->id !== $recovery->id) {
            throw ValidationException::withMessages(['account_number' => 'This recovery account already exists for the selected client and portfolio.']);
        }
        $data['identity_key'] = $identity['key'];
        $data['identity_source'] = $identity['source'];
        $data['identity_portfolio'] = (string) ($data['portfolio_type'] ?? '');

        $recovery->update($data);

        return redirect()
            ->route('recoveries.show', $recovery)
            ->with('status', 'Recovery account updated.');
    }

    public function updateAssignment(Request $request, RecoveryAccount $recovery)
    {
        abort_unless(RecoveryAccount::forBranchOf($request->user())->whereKey($recovery->id)->exists(), 403);

        $data = $request->validate([
            'assigned_to' => ['nullable', Rule::in(User::whereHas('roles', fn ($query) => $query->where('name', 'Recovery Officer'))->pluck('id')->all())],
            'branch_id' => ['nullable', 'exists:branches,id'],
        ]);

        $data = $this->applyAssignment($data, $request, $recovery);

        $recovery->update($data);

        return redirect()
            ->route('recoveries.show', $recovery)
            ->with('status', 'Recovery assignment updated.');
    }

    public function destroy(RecoveryAccount $recovery)
    {
        abort_unless(RecoveryAccount::forBranchOf(request()->user())->whereKey($recovery->id)->exists(), 403);
        abort_if($recovery->activities()->whereIn('response_outcome', ['paid', 'fully_repaid', 'write_off_requested'])->exists(), 422, 'Accounts with financial or closure records cannot be deleted.');

        $recovery->delete();

        return redirect()
            ->route('recoveries.index')
            ->with('status', 'Recovery account removed.');
    }

    public function storeSkipTrace(Request $request, RecoveryAccount $recovery)
    {
        abort_unless($this->accountVisibleTo($recovery, $request->user()), 403);

        $data = $request->validate([
            'provider' => ['required', 'in:manual,nin,nssf,crb,whatsapp'],
            'status' => ['required', 'in:pending,in_progress,found,not_found,failed'],
            'nin' => ['nullable', 'string', 'max:100'],
            'nssf_number' => ['nullable', 'string', 'max:100'],
            'crb_reference' => ['nullable', 'string', 'max:100'],
            'whatsapp_number' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $data['recovery_account_id'] = $recovery->id;
        $data['checked_by'] = $request->user()->id;
        $data['checked_at'] = now();
        $data['result'] = collect($data)->only(['nin', 'nssf_number', 'crb_reference', 'whatsapp_number'])->filter()->all();

        RecoverySkipTraceCheck::create($data);

        $accountData = [
            'skip_trace_status' => $data['status'],
            'skip_trace_checked_at' => now(),
            'skip_trace_notes' => $data['notes'] ?? null,
        ];
        foreach (['nin', 'nssf_number', 'crb_reference', 'whatsapp_number'] as $field) {
            $value = $request->input($field);
            if ($value !== null && $value !== '') {
                $accountData[$field] = $value;
            }
        }
        $recovery->update($accountData);

        return back()->with('status', 'Skip-trace check recorded.');
    }

    /**
     * Users who hold the Recovery Officer role, eligible for assignment.
     */
    private function recoveryOfficers()
    {
        return User::whereHas('roles', fn ($query) => $query->where('name', 'Recovery Officer'))
            ->with('branch')
            ->orderBy('name')
            ->get(['id', 'name', 'branch_id']);
    }

    private function accountVisibleTo(RecoveryAccount $account, User $user): bool
    {
        $query = RecoveryAccount::forBranchOf($user)->whereKey($account->id);

        if ($user->hasRole('Recovery Officer') && ! $user->can('recoveries.index')) {
            $query->where('assigned_to', $user->id);
        }

        return $query->exists();
    }

    private function mineReportFilters(Request $request): array
    {
        $grain = in_array($request->string('report_grain')->toString(), ['daily', 'weekly', 'monthly'], true)
            ? $request->string('report_grain')->toString()
            : 'daily';

        $dateFrom = $request->filled('report_date_from')
            ? Carbon::parse($request->string('report_date_from')->toString())->startOfDay()
            : now()->startOfMonth()->startOfDay();
        $dateTo = $request->filled('report_date_to')
            ? Carbon::parse($request->string('report_date_to')->toString())->endOfDay()
            : now()->endOfDay();

        if ($dateFrom->gt($dateTo)) {
            [$dateFrom, $dateTo] = [$dateTo->copy()->startOfDay(), $dateFrom->copy()->endOfDay()];
        }

        return [
            'grain' => $grain,
            'date_from' => $dateFrom->toDateString(),
            'date_to' => $dateTo->toDateString(),
            'client' => $request->filled('report_client') ? $request->integer('report_client') : null,
        ];
    }

    private function mineCollectionQuery(User $user, array $filters): Builder
    {
        $dateFrom = Carbon::parse($filters['date_from'])->startOfDay();
        $dateTo = Carbon::parse($filters['date_to'])->endOfDay();

        return RecoveryActivity::query()
            ->where('user_id', $user->id)
            ->where('review_status', 'approved')
            ->where('amount_paid', '>', 0)
            ->whereBetween('activity_at', [$dateFrom, $dateTo])
            ->whereHas('account', function (Builder $query) use ($filters) {
                if ($filters['client']) {
                    $query->where('recovery_client_id', $filters['client']);
                }
            });
    }

    private function mineCollectionSummary(User $user): array
    {
        $base = RecoveryActivity::query()
            ->where('user_id', $user->id)
            ->where('review_status', 'approved')
            ->where('amount_paid', '>', 0);
        $now = now();

        return [
            $this->collectionSummaryRow($base, 'Today', $now->format('d M Y'), $now->copy()->startOfDay(), $now->copy()->endOfDay()),
            $this->collectionSummaryRow($base, 'This Week', $now->copy()->startOfWeek()->format('d M').' - '.$now->copy()->endOfWeek()->format('d M Y'), $now->copy()->startOfWeek(), $now->copy()->endOfWeek()),
            $this->collectionSummaryRow($base, 'This Month', $now->format('F Y'), $now->copy()->startOfMonth(), $now->copy()->endOfMonth()),
        ];
    }

    private function collectionSummaryRow(Builder $base, string $label, string $period, Carbon $dateFrom, Carbon $dateTo): array
    {
        $query = (clone $base)->whereBetween('activity_at', [$dateFrom, $dateTo]);

        return [
            'label' => $label,
            'period' => $period,
            'amount' => (float) (clone $query)->sum('amount_paid'),
            'payments' => (clone $query)->count(),
        ];
    }

    private function mineCollectionChart($collections, string $grain): array
    {
        $rows = $collections
            ->groupBy(function (RecoveryActivity $activity) use ($grain) {
                $periodStart = match ($grain) {
                    'monthly' => $activity->activity_at->copy()->startOfMonth(),
                    'weekly' => $activity->activity_at->copy()->startOfWeek(),
                    default => $activity->activity_at->copy()->startOfDay(),
                };

                return $periodStart->toDateString();
            })
            ->map(function ($group, string $date) use ($grain) {
                $periodStart = Carbon::parse($date);

                return [
                    'label' => match ($grain) {
                        'monthly' => $periodStart->format('M Y'),
                        'weekly' => $periodStart->format('d M').' - '.$periodStart->copy()->endOfWeek()->format('d M'),
                        default => $periodStart->format('d M'),
                    },
                    'amount' => (float) $group->sum('amount_paid'),
                    'payments' => $group->count(),
                    'sort_key' => $periodStart->timestamp,
                ];
            })
            ->sortBy('sort_key')
            ->values();

        $max = max((float) $rows->max('amount'), 1);

        return [
            'rows' => $rows->map(fn (array $row) => $row + ['percent' => max(4, round(($row['amount'] / $max) * 100))])->all(),
            'max' => $max,
        ];
    }

    private function mineBankChart($collections): array
    {
        $colors = ['#071426', '#0f766e', '#b45309', '#2563eb', '#7c3aed', '#be123c'];
        $rows = $collections
            ->groupBy(fn (RecoveryActivity $activity) => $activity->account?->client?->name ?: 'Unknown')
            ->map(fn ($group, string $client) => [
                'client' => $client,
                'amount' => (float) $group->sum('amount_paid'),
                'payments' => $group->count(),
            ])
            ->sortByDesc('amount')
            ->values();
        $total = max((float) $rows->sum('amount'), 0);
        $cursor = 0.0;
        $segments = [];

        foreach ($rows as $index => $row) {
            $share = $total > 0 ? ($row['amount'] / $total) * 100 : 0;
            $start = $cursor;
            $cursor += $share;
            $segments[] = $colors[$index % count($colors)].' '.$start.'% '.$cursor.'%';
            $rows[$index] = $row + [
                'color' => $colors[$index % count($colors)],
                'share' => $share,
            ];
        }

        return [
            'rows' => $rows->all(),
            'total' => $total,
            'gradient' => $segments ? implode(', ', $segments) : '#eef2f7 0% 100%',
        ];
    }

    /**
     * Stamp the branch + assignment metadata from the chosen officer.
     */
    private function applyAssignment(array $data, Request $request, ?RecoveryAccount $existing = null): array
    {
        $assignedTo = $data['assigned_to'] ?? null;
        $previousAssignee = $existing?->assigned_to;

        if ($assignedTo && $assignedTo != $previousAssignee) {
            $officer = User::whereKey($assignedTo)
                ->whereHas('roles', fn ($query) => $query->where('name', 'Recovery Officer'))
                ->first();
            abort_unless($officer, 422, 'Only users with the Recovery Officer role can be assigned recovery work.');
            $data['assigned_by'] = $request->user()->id;
            $data['assigned_at'] = now();
        }

        // Default the branch from the assigned officer when not explicitly set.
        if (empty($data['branch_id']) && $assignedTo) {
            $data['branch_id'] = User::find($assignedTo)?->branch_id;
        }

        return $data;
    }

    private function validateAccount(Request $request, bool $includeAssignment = true, ?RecoveryAccount $existing = null): array
    {
        $expectedStatus = $existing?->status ?? 'active';
        if ($request->has('status') && $request->input('status') !== $expectedStatus) {
            throw ValidationException::withMessages(['status' => 'Use the reviewed payment or closure workflow to change account status.']);
        }
        $request->merge([
            'pipeline_stage' => $request->input('pipeline_stage', $existing?->pipeline_stage ?: 'new'),
            'priority' => $request->input('priority', $existing?->priority ?: 'normal'),
            'skip_trace_status' => $request->input('skip_trace_status', $existing?->skip_trace_status ?: 'not_started'),
            'sms_opt_in' => $request->has('sms_opt_in') ? $request->boolean('sms_opt_in') : ($existing?->sms_opt_in ?? false),
            'status' => $expectedStatus,
        ]);

        $rules = [
            'recovery_client_id' => ['required', Rule::exists('recovery_clients', 'id')->where('is_active', true)],
            'debtor_name' => ['required', 'string', 'max:191'],
            'account_number' => ['nullable', 'string', 'max:100'],
            'customer_number' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:50'],
            'alternate_phone' => ['nullable', 'string', 'max:50'],
            'whatsapp_number' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:191'],
            'nin' => ['nullable', 'string', 'max:100'],
            'nssf_number' => ['nullable', 'string', 'max:100'],
            'crb_reference' => ['nullable', 'string', 'max:100'],
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
            'pipeline_stage' => ['required', Rule::in(array_keys(RecoveryAccount::PIPELINE_STAGES))],
            'priority' => ['required', Rule::in(array_keys(RecoveryAccount::PRIORITIES))],
            'last_contact_at' => ['nullable', 'date'],
            'next_follow_up_at' => ['nullable', 'date'],
            'skip_trace_status' => ['required', Rule::in(array_keys(RecoveryAccount::SKIP_TRACE_STATUSES))],
            'skip_trace_notes' => ['nullable', 'string', 'max:2000'],
            'sms_opt_in' => ['nullable', 'boolean'],
            'collateral_held' => ['nullable', 'string', 'max:2000'],
            'cause_of_default' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', 'in:'.implode(',', array_keys(RecoveryAccount::STATUSES))],
        ];

        if ($includeAssignment) {
            $rules['assigned_to'] = ['nullable', 'exists:users,id'];
            $rules['branch_id'] = ['nullable', 'exists:branches,id'];
        }

        $data = $request->validate($rules);

        if (in_array($data['pipeline_stage'], ['closed', 'recovered'], true)
            && $data['pipeline_stage'] !== $existing?->pipeline_stage) {
            throw ValidationException::withMessages(['pipeline_stage' => 'Closure and recovery stages are set by an approved decision.']);
        }
        if ($existing && $existing->activities()->where('review_status', 'approved')->where('response_outcome', 'paid')->exists()
            && ((float) ($data['outstanding_amount'] ?? 0) !== (float) $existing->outstanding_amount
                || ($data['currency'] ?? 'UGX') !== $existing->currency)) {
            throw ValidationException::withMessages(['outstanding_amount' => 'The opening balance and currency cannot be edited after an approved payment. Use the correction workflow.']);
        }

        return RecoveryReferenceData::validate($data, RecoveryClient::findOrFail($data['recovery_client_id']));
    }

    private function batchVisibleTo(RecoveryImportBatch $batch, User $user): bool
    {
        return $user->canSeeAllBranches()
            || $batch->uploaded_by === $user->id
            || $batch->accounts()->forBranchOf($user)->exists();
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
            ->when($request->filled('portfolio_type'), fn ($query) => $query->where('portfolio_type', $request->string('portfolio_type')->toString()))
            ->when($request->filled('pipeline_stage'), fn ($query) => $query->where('pipeline_stage', $request->string('pipeline_stage')->toString()));
    }

    private function portfolioTypes(): array
    {
        return RecoveryClient::query()
            ->get(['portfolio_types'])
            ->flatMap(fn (RecoveryClient $client) => $client->portfolio_types ?: [])
            ->unique()
            ->sort()
            ->values()
            ->all();
    }
}
