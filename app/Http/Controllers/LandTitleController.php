<?php

namespace App\Http\Controllers;

use App\Exports\LandTitlesExport;
use App\Exports\RecoveryReportExport;
use App\Imports\LandTitlesImport;
use App\Models\Bank;
use App\Models\BankBranch;
use App\Models\LandTitle;
use App\Models\Matter;
use App\Models\User;
use App\Models\ZonalOffice;
use App\Support\MonthlyReferenceNumber;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class LandTitleController extends Controller
{
    public function dashboard()
    {
        return view('modules.land-titles.dashboard', $this->dashboardData());
    }

    public function dashboardExport(string $section)
    {
        [$headings, $rows, $title] = $this->dashboardDataset($section);

        return Excel::download(
            new RecoveryReportExport($headings, $rows, $title),
            'securities-'.$section.'-'.now()->format('Ymd-His').'.xlsx'
        );
    }

    private function dashboardData(): array
    {
        $activeStatuses = ['pending', 'received', 'in_progress', 'dispatched'];
        $titles = LandTitle::query()
            ->with(['bank', 'bankBranch', 'zonalOffice', 'handler'])
            ->get();

        $statusRows = collect(LandTitle::STATUSES)->map(fn ($label, $status) => [
            'status' => $status,
            'label' => $label,
            'count' => $titles->where('status', $status)->count(),
        ])->values();

        return [
            'summary' => [
                'total' => $titles->count(),
                'in_custody' => $titles->whereIn('status', $activeStatuses)->count(),
                'pending' => $titles->where('status', 'pending')->count(),
                'dispatched' => $titles->where('status', 'dispatched')->count(),
                'returned' => $titles->where('status', 'returned')->count(),
                'received_this_month' => $titles
                    ->filter(fn (LandTitle $title) => $title->received_at?->isSameMonth(now()))
                    ->count(),
            ],
            'statusRows' => $statusRows,
            'bankRows' => $titles
                ->groupBy(fn (LandTitle $title) => $title->bank?->name ?: 'No institution')
                ->map(fn ($group, $bank) => [
                    'bank' => $bank,
                    'count' => $group->count(),
                    'in_custody' => $group->whereIn('status', $activeStatuses)->count(),
                    'returned' => $group->where('status', 'returned')->count(),
                ])
                ->sortByDesc('count')
                ->take(6)
                ->values(),
            'zonalRows' => $titles
                ->groupBy(fn (LandTitle $title) => $title->zonalOffice?->name ?: 'No MZO')
                ->map(fn ($group, $office) => [
                    'office' => $office,
                    'count' => $group->count(),
                    'in_custody' => $group->whereIn('status', $activeStatuses)->count(),
                ])
                ->sortByDesc('count')
                ->take(6)
                ->values(),
            'recentTitles' => LandTitle::query()
                ->with(['bank', 'bankBranch', 'zonalOffice', 'handler'])
                ->latest()
                ->limit(8)
                ->get(),
            'custodyQueue' => LandTitle::query()
                ->with(['bank', 'bankBranch', 'zonalOffice', 'handler'])
                ->whereIn('status', $activeStatuses)
                ->orderByRaw('received_at is null, received_at asc')
                ->limit(8)
                ->get(),
        ];
    }

    public function index(Request $request)
    {
        $titles = LandTitle::query()
            ->with(['bank', 'bankBranch', 'zonalOffice', 'handler'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search) {
                    $query
                        ->where('reference_no', 'like', "%{$search}%")
                        ->orWhere('borrower_name', 'like', "%{$search}%")
                        ->orWhere('instruction_type', 'like', "%{$search}%")
                        ->orWhere('received_from', 'like', "%{$search}%")
                        ->orWhere('returned_to', 'like', "%{$search}%")
                        ->orWhereHas('bank', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('bankBranch', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('zonalOffice', fn ($query) => $query->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('modules.land-titles.index', [
            'titles' => $titles,
            'filters' => $request->only(['search', 'status']),
            'statuses' => LandTitle::STATUSES,
        ]);
    }

    public function create()
    {
        return view('modules.land-titles.create', $this->formData([
            'title' => new LandTitle([
                'reference_no' => MonthlyReferenceNumber::make(LandTitle::class, 'reference_no', 'SEC'),
                'status' => 'pending',
                'received_at' => now(),
            ]),
        ]));
    }

    public function importForm()
    {
        return view('modules.land-titles.import');
    }

    public function importStore(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:20480'],
        ]);

        $import = new LandTitlesImport;

        Excel::import($import, $request->file('file'));

        return redirect()
            ->route('land-titles.index')
            ->with('status', "Imported {$import->imported} security record(s); skipped {$import->skipped}.")
            ->with('import_errors', array_slice($import->errors, 0, 10));
    }

    public function store(Request $request)
    {
        $data = $this->validateSecurity($request);
        unset($data['documents']);
        $data['reference_no'] = MonthlyReferenceNumber::make(LandTitle::class, 'reference_no', 'SEC');

        $title = LandTitle::create($data);
        $this->storeDocuments($request, $title);

        return redirect()
            ->route('land-titles.show', $title)
            ->with('status', 'Security registered.');
    }

    public function show(LandTitle $landTitle)
    {
        return view('modules.land-titles.show', [
            'title' => $landTitle->load(['bank', 'bankBranch', 'zonalOffice', 'matter.client', 'handler', 'attachments.uploader']),
        ]);
    }

    public function edit(LandTitle $landTitle)
    {
        return view('modules.land-titles.edit', $this->formData([
            'title' => $landTitle,
        ]));
    }

    public function update(Request $request, LandTitle $landTitle)
    {
        $data = $this->validateSecurity($request, $landTitle);
        unset($data['documents']);

        $landTitle->update($data);
        $this->storeDocuments($request, $landTitle);

        return redirect()
            ->route('land-titles.show', $landTitle)
            ->with('status', 'Security updated.');
    }

    public function returnForm(LandTitle $landTitle)
    {
        abort_if(in_array($landTitle->status, ['returned', 'closed'], true), 404);

        return view('modules.land-titles.return', [
            'title' => $landTitle->load(['bank', 'bankBranch', 'zonalOffice', 'handler']),
        ]);
    }

    public function returnSecurity(Request $request, LandTitle $landTitle)
    {
        abort_if(in_array($landTitle->status, ['returned', 'closed'], true), 404);

        $data = $request->validate([
            'returned_to' => ['required', 'string', 'max:191'],
            'returned_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'documents' => ['nullable', 'array'],
            'documents.*' => ['file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx'],
        ]);

        $landTitle->update([
            'status' => 'returned',
            'returned_to' => $data['returned_to'],
            'returned_at' => $data['returned_at'],
            'notes' => $data['notes'] ?? $landTitle->notes,
        ]);

        $this->storeDocuments($request, $landTitle);

        return redirect()
            ->route('land-titles.show', $landTitle)
            ->with('status', 'Security marked as returned.');
    }

    public function destroy(LandTitle $landTitle)
    {
        $landTitle->delete();

        return redirect()
            ->route('land-titles.index')
            ->with('status', 'Security deleted.');
    }

    public function export(Request $request)
    {
        return Excel::download(
            new LandTitlesExport($request->only(['search', 'status'])),
            'securities-'.now()->format('Ymd-His').'.xlsx'
        );
    }

    private function dashboardDataset(string $section): array
    {
        $data = $this->dashboardData();

        return match ($section) {
            'status' => [
                ['Status', 'Records'],
                $data['statusRows']->map(fn ($row) => [$row['label'], $row['count']])->all(),
                'Securities Status Breakdown',
            ],
            'banks' => [
                ['Financial Institution', 'Total', 'In Custody', 'Returned'],
                $data['bankRows']->map(fn ($row) => [$row['bank'], $row['count'], $row['in_custody'], $row['returned']])->all(),
                'By Institution',
            ],
            'zonal-offices' => [
                ['MZO / Zonal Office', 'Total', 'In Custody'],
                $data['zonalRows']->map(fn ($row) => [$row['office'], $row['count'], $row['in_custody']])->all(),
                'By Zonal Office',
            ],
            'recent' => [
                ['Reference', 'Borrower', 'Institution', 'Branch', 'MZO', 'Received', 'Status'],
                $data['recentTitles']->map(fn (LandTitle $title) => [
                    $title->reference_no,
                    $title->borrower_name,
                    $title->bank?->name,
                    $title->bankBranch?->name,
                    $title->zonalOffice?->name,
                    $title->received_at?->format('Y-m-d H:i'),
                    $title->statusLabel(),
                ])->all(),
                'Recent Securities',
            ],
            default => [
                ['Reference', 'Borrower', 'Institution', 'Handler', 'Received', 'Status'],
                $data['custodyQueue']->map(fn (LandTitle $title) => [
                    $title->reference_no,
                    $title->borrower_name,
                    $title->bank?->name,
                    $title->handler?->name,
                    $title->received_at?->format('Y-m-d H:i'),
                    $title->statusLabel(),
                ])->all(),
                'Custody Queue',
            ],
        };
    }

    private function formData(array $overrides = []): array
    {
        return array_merge([
            'banks' => Bank::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            'bankBranches' => BankBranch::with('bank')
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'bank_id', 'name', 'office_location']),
            'zonalOffices' => ZonalOffice::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'office_location']),
            'matters' => Matter::with('client')->latest()->limit(200)->get(['id', 'reference_no', 'title', 'client_id']),
            'handlers' => $this->staffHandlers(),
            'statuses' => LandTitle::STATUSES,
        ], $overrides);
    }

    private function staffHandlers()
    {
        return User::query()
            ->where(function ($query) {
                $query
                    ->whereNull('account_type')
                    ->orWhere('account_type', 'staff');
            })
            ->whereHas('staffProfile', fn ($query) => $query->where('employment_status', 'active'))
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }

    private function validateSecurity(Request $request, ?LandTitle $landTitle = null): array
    {
        $bankBranchRule = Rule::exists('bank_branches', 'id');

        if ($request->filled('bank_id')) {
            $bankBranchRule->where('bank_id', $request->integer('bank_id'));
        }

        $allowedStatuses = collect(array_keys(LandTitle::STATUSES))
            ->reject(fn (string $status) => $status === 'returned' && $landTitle?->status !== 'returned')
            ->values()
            ->all();

        return $request->validate([
            'bank_id' => ['nullable', 'exists:banks,id'],
            'bank_branch_id' => ['nullable', $bankBranchRule],
            'zonal_office_id' => ['nullable', 'exists:zonal_offices,id'],
            'matter_id' => ['nullable', 'exists:matters,id'],
            'handled_by' => ['nullable', 'exists:users,id'],
            'borrower_name' => ['required', 'string', 'max:191'],
            'instruction_type' => ['nullable', 'string', 'max:191'],
            'instruction_date' => ['nullable', 'date'],
            'received_from' => ['nullable', 'string', 'max:191'],
            'received_at' => ['nullable', 'date'],
            'dispatched_at' => ['nullable', 'date'],
            'status' => ['required', Rule::in($allowedStatuses)],
            'notes' => ['nullable', 'string', 'max:3000'],
            'documents' => ['nullable', 'array'],
            'documents.*' => ['file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx'],
        ]);
    }

    private function storeDocuments(Request $request, LandTitle $title): void
    {
        foreach ($request->file('documents', []) as $document) {
            $title->addAttachment($document, [
                'category' => 'security_document',
                'title' => 'Security document',
            ]);
        }
    }
}
