<?php

namespace App\Http\Controllers;

use App\Exports\LandTitlesExport;
use App\Exports\RecoveryReportExport;
use App\Imports\LandTitlesImport;
use App\Models\Attachment;
use App\Models\Bank;
use App\Models\BankBranch;
use App\Models\LandTitle;
use App\Models\LandTitleChecklistItem;
use App\Models\LandTitleMilestone;
use App\Models\LandTitleMovement;
use App\Models\Matter;
use App\Models\User;
use App\Models\ZonalOffice;
use App\Support\MonthlyReferenceNumber;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class LandTitleController extends Controller
{
    public function dashboard(Request $request)
    {
        return view('modules.land-titles.dashboard', $this->dashboardData($request->user()));
    }

    public function dashboardExport(Request $request, string $section)
    {
        [$headings, $rows, $title] = $this->dashboardDataset($section, $request->user());

        return Excel::download(
            new RecoveryReportExport($headings, $rows, $title),
            'securities-'.$section.'-'.now()->format('Ymd-His').'.xlsx'
        );
    }

    private function dashboardData(?User $user): array
    {
        $activeStatuses = ['pending', 'received', 'in_progress', 'dispatched'];
        $titles = LandTitle::query()->forBranchOf($user)
            ->with(['bank', 'bankBranch', 'zonalOffice', 'handler'])
            ->get();
        $milestones = LandTitleMilestone::query()
            ->with(['landTitle.handler'])
            ->where('status', 'pending')
            ->whereHas('landTitle', fn ($query) => $query->forBranchOf($user))
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
                'overdue_milestones' => $milestones->filter(fn (LandTitleMilestone $milestone) => $milestone->isOverdue())->count(),
                'upcoming_milestones' => $milestones->filter(fn (LandTitleMilestone $milestone) => ! $milestone->isOverdue())->count(),
            ],
            'milestoneQueue' => $milestones->sortBy(fn (LandTitleMilestone $milestone) => $milestone->due_at?->timestamp ?: PHP_INT_MAX)->take(8)->values(),
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
            'recentTitles' => LandTitle::query()->forBranchOf($user)
                ->with(['bank', 'bankBranch', 'zonalOffice', 'handler'])
                ->latest()
                ->limit(8)
                ->get(),
            'custodyQueue' => LandTitle::query()->forBranchOf($user)
                ->with(['bank', 'bankBranch', 'zonalOffice', 'handler'])
                ->whereIn('status', $activeStatuses)
                ->orderByRaw('received_at is null, received_at asc')
                ->limit(8)
                ->get(),
        ];
    }

    public function index(Request $request)
    {
        $titles = LandTitle::query()->forBranchOf($request->user())
            ->with(['bank', 'bankBranch', 'zonalOffice', 'handler'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search) {
                    $query
                        ->where('reference_no', 'like', "%{$search}%")
                        ->orWhere('borrower_name', 'like', "%{$search}%")
                        ->orWhere('title_number', 'like', "%{$search}%")
                        ->orWhere('land_reference', 'like', "%{$search}%")
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

    public function create(Request $request)
    {
        return view('modules.land-titles.create', $this->formData([
            'title' => new LandTitle([
                'reference_no' => MonthlyReferenceNumber::make(LandTitle::class, 'reference_no', 'SEC'),
                'status' => 'pending',
                'movement_direction' => 'incoming',
                'branch_id' => $request->user()->branch_id,
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

        $import = new LandTitlesImport($request->user());

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
        $data['branch_id'] = $data['branch_id'] ?? $request->user()->branch_id;

        $title = LandTitle::create($data);
        $this->seedChecklist($title);
        $this->storeDocuments($request, $title);

        return redirect()
            ->route('land-titles.show', $title)
            ->with('status', 'Security registered.');
    }

    public function show(Request $request, LandTitle $landTitle)
    {
        $this->ensureVisible($request, $landTitle);

        return view('modules.land-titles.show', [
            'title' => $landTitle->load(['branch', 'bank', 'bankBranch', 'zonalOffice', 'matter.client', 'handler', 'attachments.uploader', 'movements.recorder', 'checklistItems.completer', 'checklistItems.evidenceAttachment', 'milestones.completer']),
            'movementTypes' => LandTitleMovement::TYPES,
            'checklistStatuses' => LandTitleChecklistItem::STATUSES,
            'milestoneStatuses' => LandTitleMilestone::STATUSES,
        ]);
    }

    public function edit(Request $request, LandTitle $landTitle)
    {
        $this->ensureVisible($request, $landTitle);

        return view('modules.land-titles.edit', $this->formData([
            'title' => $landTitle,
        ]));
    }

    public function update(Request $request, LandTitle $landTitle)
    {
        $this->ensureVisible($request, $landTitle);
        $data = $this->validateSecurity($request, $landTitle);
        unset($data['documents']);

        if (! $landTitle->canTransitionTo($data['status'])) {
            throw ValidationException::withMessages([
                'status' => "A security cannot move from {$landTitle->statusLabel()} to ".(LandTitle::STATUSES[$data['status']] ?? $data['status']).'.',
            ]);
        }

        if ($data['status'] === 'closed' && $landTitle->checklistItems()->where('is_required', true)->whereNotIn('status', ['complete', 'waived'])->exists()) {
            throw ValidationException::withMessages([
                'status' => 'Complete or waive every required checklist item before closing the security.',
            ]);
        }

        $previousStatus = $landTitle->status;
        $data['closed_at'] = $data['status'] === 'closed' ? ($landTitle->closed_at ?: now()) : null;

        $landTitle->update($data);
        $this->recordStatusMovement($landTitle, $previousStatus, $request->user());
        $this->storeDocuments($request, $landTitle);

        return redirect()
            ->route('land-titles.show', $landTitle)
            ->with('status', 'Security updated.');
    }

    public function returnForm(Request $request, LandTitle $landTitle)
    {
        $this->ensureVisible($request, $landTitle);
        abort_unless($landTitle->status === 'dispatched', 422, 'Only dispatched securities can be returned.');

        return view('modules.land-titles.return', [
            'title' => $landTitle->load(['bank', 'bankBranch', 'zonalOffice', 'handler']),
        ]);
    }

    public function returnSecurity(Request $request, LandTitle $landTitle)
    {
        $this->ensureVisible($request, $landTitle);
        abort_unless($landTitle->status === 'dispatched', 422, 'Only dispatched securities can be returned.');

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

        $landTitle->movements()->create([
            'movement_type' => 'returned',
            'from_party' => $landTitle->dispatched_to ?: 'External recipient',
            'to_party' => $data['returned_to'],
            'moved_at' => $data['returned_at'],
            'recorded_by' => $request->user()->id,
            'acknowledged_by' => $data['returned_to'],
            'acknowledged_at' => $data['returned_at'],
            'notes' => $data['notes'] ?? null,
        ]);

        $this->storeDocuments($request, $landTitle);

        return redirect()
            ->route('land-titles.show', $landTitle)
            ->with('status', 'Security marked as returned.');
    }

    public function destroy(Request $request, LandTitle $landTitle)
    {
        $this->ensureVisible($request, $landTitle);
        $landTitle->delete();

        return redirect()
            ->route('land-titles.index')
            ->with('status', 'Security deleted.');
    }

    public function export(Request $request)
    {
        return Excel::download(
            new LandTitlesExport($request->user(), $request->only(['search', 'status'])),
            'securities-'.now()->format('Ymd-His').'.xlsx'
        );
    }

    private function dashboardDataset(string $section, ?User $user): array
    {
        $data = $this->dashboardData($user);

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
            'matters' => Matter::visibleTo(request()->user())->with('client')->latest()->limit(200)->get(['id', 'reference_no', 'title', 'client_id']),
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
        $request->merge([
            'security_type' => $request->input('security_type') ?: 'land_title',
            'movement_direction' => $request->input('movement_direction') ?: 'incoming',
        ]);

        $bankBranchRule = Rule::exists('bank_branches', 'id');

        if ($request->filled('bank_id')) {
            $bankBranchRule->where('bank_id', $request->integer('bank_id'));
        }

        $blockedCreateStatuses = $landTitle ? [] : ['dispatched', 'returned', 'closed'];
        $allowedStatuses = collect(array_keys(LandTitle::STATUSES))
            ->reject(fn (string $status) => in_array($status, $blockedCreateStatuses, true))
            ->reject(fn (string $status) => $status === 'returned' && $landTitle?->status !== 'returned')
            ->values()
            ->all();

        $data = $request->validate([
            'branch_id' => ['nullable', 'exists:branches,id'],
            'bank_id' => ['nullable', 'exists:banks,id'],
            'bank_branch_id' => ['nullable', $bankBranchRule],
            'zonal_office_id' => ['nullable', 'exists:zonal_offices,id'],
            'matter_id' => ['nullable', 'exists:matters,id'],
            'handled_by' => ['nullable', 'exists:users,id'],
            'security_type' => ['required', Rule::in(['land_title', 'mortgage', 'discharge', 'consent', 'other'])],
            'movement_direction' => ['required', Rule::in(['incoming', 'outgoing'])],
            'borrower_name' => ['required', 'string', 'max:191'],
            'borrower_contact' => ['nullable', 'string', 'max:100'],
            'title_number' => ['nullable', 'string', 'max:191'],
            'land_reference' => ['nullable', 'string', 'max:191'],
            'tenure' => ['nullable', 'string', 'max:191'],
            'district' => ['nullable', 'string', 'max:191'],
            'facility_account_no' => ['nullable', 'string', 'max:100'],
            'instruction_type' => ['nullable', 'string', 'max:191'],
            'instruction_details' => ['nullable', 'string', 'max:5000'],
            'instruction_date' => ['nullable', 'date'],
            'received_from' => ['nullable', 'string', 'max:191'],
            'dispatched_to' => ['nullable', 'string', 'max:191'],
            'received_at' => ['nullable', 'date'],
            'dispatched_at' => ['nullable', 'date'],
            'dispatch_acknowledged_by' => ['nullable', 'string', 'max:191'],
            'dispatch_acknowledged_at' => ['nullable', 'date'],
            'status' => ['required', Rule::in($allowedStatuses)],
            'notes' => ['nullable', 'string', 'max:3000'],
            'documents' => ['nullable', 'array'],
            'documents.*' => ['file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx'],
        ]);

        if (! empty($data['matter_id']) && ! Matter::visibleTo($request->user())->whereKey($data['matter_id'])->exists()) {
            throw ValidationException::withMessages([
                'matter_id' => 'Select a matter you are allowed to access.',
            ]);
        }

        if (! empty($data['branch_id']) && ! $request->user()->canSeeAllBranches() && (int) $data['branch_id'] !== (int) $request->user()->branch_id) {
            throw ValidationException::withMessages([
                'branch_id' => 'Select a branch you are allowed to work in.',
            ]);
        }

        return $data;
    }

    public function storeMovement(Request $request, LandTitle $landTitle)
    {
        $this->ensureVisible($request, $landTitle);

        $data = $request->validate([
            'movement_type' => ['required', Rule::in(array_keys(LandTitleMovement::TYPES))],
            'from_party' => ['nullable', 'string', 'max:191'],
            'to_party' => ['nullable', 'string', 'max:191'],
            'moved_at' => ['required', 'date'],
            'acknowledged_by' => ['nullable', 'string', 'max:191'],
            'acknowledged_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);

        $status = match ($data['movement_type']) {
            'received' => 'received',
            'dispatched' => 'dispatched',
            'returned' => 'returned',
            default => $landTitle->status,
        };

        if ($status !== $landTitle->status) {
            if (! $landTitle->canTransitionTo($status)) {
                throw ValidationException::withMessages(['movement_type' => 'That movement is not valid from the current security status.']);
            }

            $landTitle->update([
                'status' => $status,
                'received_at' => $status === 'received' ? $data['moved_at'] : $landTitle->received_at,
                'dispatched_at' => $status === 'dispatched' ? $data['moved_at'] : $landTitle->dispatched_at,
                'returned_at' => $status === 'returned' ? $data['moved_at'] : $landTitle->returned_at,
                'dispatched_to' => $status === 'dispatched' ? ($data['to_party'] ?? $landTitle->dispatched_to) : $landTitle->dispatched_to,
                'returned_to' => $status === 'returned' ? ($data['to_party'] ?? $landTitle->returned_to) : $landTitle->returned_to,
            ]);
        }

        $data['recorded_by'] = $request->user()->id;
        $landTitle->movements()->create($data);

        return back()->with('status', 'Security movement recorded.');
    }

    public function storeChecklistItem(Request $request, LandTitle $landTitle)
    {
        $this->ensureVisible($request, $landTitle);
        $data = $request->validate([
            'label' => ['required', 'string', 'max:191'],
            'is_required' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $data['is_required'] = $request->boolean('is_required', true);
        $data['sort_order'] = ((int) $landTitle->checklistItems()->max('sort_order')) + 1;
        $landTitle->checklistItems()->create($data);

        return back()->with('status', 'Checklist item added.');
    }

    public function updateChecklistItem(Request $request, LandTitle $landTitle, LandTitleChecklistItem $item)
    {
        $this->ensureVisible($request, $landTitle);
        abort_unless($item->land_title_id === $landTitle->id, 404);
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(LandTitleChecklistItem::STATUSES))],
            'evidence_attachment_id' => ['nullable', 'exists:attachments,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        if (! empty($data['evidence_attachment_id']) && ! Attachment::query()->whereKey($data['evidence_attachment_id'])->where('attachable_type', LandTitle::class)->where('attachable_id', $landTitle->id)->exists()) {
            throw ValidationException::withMessages(['evidence_attachment_id' => 'Select a document attached to this security.']);
        }
        $item->update([
            'status' => $data['status'],
            'notes' => $data['notes'] ?? $item->notes,
            'evidence_attachment_id' => $data['evidence_attachment_id'] ?? $item->evidence_attachment_id,
            'completed_by' => in_array($data['status'], ['complete', 'waived'], true) ? $request->user()->id : null,
            'completed_at' => in_array($data['status'], ['complete', 'waived'], true) ? now() : null,
        ]);

        return back()->with('status', 'Checklist item updated.');
    }

    public function destroyChecklistItem(Request $request, LandTitle $landTitle, LandTitleChecklistItem $item)
    {
        $this->ensureVisible($request, $landTitle);
        abort_unless($item->land_title_id === $landTitle->id, 404);
        $item->delete();

        return back()->with('status', 'Checklist item removed.');
    }

    public function storeMilestone(Request $request, LandTitle $landTitle)
    {
        $this->ensureVisible($request, $landTitle);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'due_at' => ['nullable', 'date'],
            'reminder_days_before' => ['required', 'integer', 'min:0', 'max:365'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $data['sort_order'] = ((int) $landTitle->milestones()->max('sort_order')) + 1;
        $landTitle->milestones()->create($data);

        return back()->with('status', 'Milestone added.');
    }

    public function updateMilestone(Request $request, LandTitle $landTitle, LandTitleMilestone $milestone)
    {
        $this->ensureVisible($request, $landTitle);
        abort_unless($milestone->land_title_id === $landTitle->id, 404);
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(LandTitleMilestone::STATUSES))],
            'due_at' => ['nullable', 'date'],
            'reminder_days_before' => ['required', 'integer', 'min:0', 'max:365'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $milestone->update([
            ...$data,
            'completed_by' => $data['status'] === 'complete' ? $request->user()->id : null,
            'completed_at' => $data['status'] === 'complete' ? now() : null,
        ]);

        return back()->with('status', 'Milestone updated.');
    }

    public function destroyMilestone(Request $request, LandTitle $landTitle, LandTitleMilestone $milestone)
    {
        $this->ensureVisible($request, $landTitle);
        abort_unless($milestone->land_title_id === $landTitle->id, 404);
        $milestone->delete();

        return back()->with('status', 'Milestone removed.');
    }

    private function seedChecklist(LandTitle $landTitle): void
    {
        foreach (['Original title / security received', 'Supporting documents checked', 'Dispatch or return acknowledgement filed'] as $order => $label) {
            $landTitle->checklistItems()->create([
                'label' => $label,
                'is_required' => true,
                'sort_order' => $order + 1,
            ]);
        }
    }

    private function recordStatusMovement(LandTitle $landTitle, string $previousStatus, User $user): void
    {
        if ($previousStatus === $landTitle->status) {
            return;
        }

        $movementType = match ($landTitle->status) {
            'received' => 'received',
            'dispatched' => 'dispatched',
            'returned' => 'returned',
            default => null,
        };

        if (! $movementType) {
            return;
        }

        $landTitle->movements()->create([
            'movement_type' => $movementType,
            'from_party' => $movementType === 'received' ? $landTitle->received_from : null,
            'to_party' => $movementType === 'dispatched' ? $landTitle->dispatched_to : ($movementType === 'returned' ? $landTitle->returned_to : null),
            'moved_at' => match ($movementType) {
                'received' => $landTitle->received_at ?: now(),
                'dispatched' => $landTitle->dispatched_at ?: now(),
                default => $landTitle->returned_at ?: now(),
            },
            'recorded_by' => $user->id,
            'acknowledged_by' => $movementType === 'dispatched' ? $landTitle->dispatch_acknowledged_by : null,
            'acknowledged_at' => $movementType === 'dispatched' ? $landTitle->dispatch_acknowledged_at : null,
        ]);
    }

    private function ensureVisible(Request $request, LandTitle $landTitle): void
    {
        abort_unless(LandTitle::query()->forBranchOf($request->user())->whereKey($landTitle->id)->exists(), 403);
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
