<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Matter;
use App\Models\Requisition;
use App\Models\RequisitionCategory;
use App\Services\ApprovalService;
use App\Support\MonthlyReferenceNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RequisitionController extends Controller
{
    public function index(Request $request)
    {
        $canApprove = $request->user()->can('requisitions.approve');

        $requisitions = Requisition::with(['requester', 'category', 'matter', 'reviewer'])
            ->when(! $canApprove, fn ($query) => $query->where('requested_by', $request->user()->id))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search) {
                    $query
                        ->where('reference_no', 'like', "%{$search}%")
                        ->orWhere('purpose', 'like', "%{$search}%")
                        ->orWhereHas('requester', fn ($query) => $query->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('requisition_category_id'), fn ($query) => $query->where('requisition_category_id', $request->integer('requisition_category_id')))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $scope = Requisition::query()
            ->when(! $canApprove, fn ($query) => $query->where('requested_by', $request->user()->id));

        $summary = [
            'Total Requests' => (clone $scope)->count(),
            'Pending' => (clone $scope)->where('status', 'submitted')->count(),
            'Approved' => (clone $scope)->where('status', 'approved')->count(),
            'Rejected' => (clone $scope)->where('status', 'rejected')->count(),
            'Approved Value' => (clone $scope)->where('status', 'approved')->sum('amount'),
        ];

        return view('modules.requisitions.index', [
            'requisitions' => $requisitions,
            'filters' => $request->only(['search', 'status', 'requisition_category_id']),
            'statuses' => Requisition::STATUSES,
            'categories' => RequisitionCategory::orderBy('sort_order')->orderBy('name')->get(),
            'canApprove' => $canApprove,
            'summary' => $summary,
        ]);
    }

    public function create()
    {
        return view('modules.requisitions.create', [
            'referenceNumber' => MonthlyReferenceNumber::make(Requisition::class, 'reference_no', 'RQ'),
            'categories' => RequisitionCategory::orderBy('sort_order')->orderBy('name')->get(),
            'matters' => Matter::orderByDesc('id')->limit(200)->get(['id', 'reference_no', 'title']),
        ]);
    }

    public function store(Request $request, ApprovalService $approvals)
    {
        $data = $request->validate([
            'requisition_category_id' => ['nullable', 'exists:requisition_categories,id'],
            'matter_id' => ['nullable', 'exists:matters,id'],
            'purpose' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'attachment' => ['nullable', 'file', 'max:5120'],
        ]);

        $requisition = DB::transaction(function () use ($data, $request, $approvals) {
            $requisition = Requisition::create([
                'reference_no' => MonthlyReferenceNumber::make(Requisition::class, 'reference_no', 'RQ'),
                'requested_by' => $request->user()->id,
                'requisition_category_id' => $data['requisition_category_id'] ?? null,
                'matter_id' => $data['matter_id'] ?? null,
                'purpose' => $data['purpose'],
                'amount' => $data['amount'],
                'notes' => $data['notes'] ?? null,
                'status' => 'submitted',
            ]);

            if ($request->hasFile('attachment')) {
                $requisition->addAttachment($request->file('attachment'), ['category' => 'requisition-support']);
            }

            $approvals->submit($requisition, $request->user());

            return $requisition;
        });

        return redirect()
            ->route('requisitions.show', $requisition)
            ->with('status', 'Requisition '.$requisition->reference_no.' submitted for approval.');
    }

    public function show(Request $request, Requisition $requisition)
    {
        abort_unless(
            $requisition->requested_by === $request->user()->id || $request->user()->can('requisitions.approve'),
            403
        );

        return view('modules.requisitions.show', [
            'requisition' => $requisition->load(['requester', 'category', 'matter', 'reviewer', 'attachments', 'approvals.approver']),
            'canApprove' => $request->user()->can('requisitions.approve'),
        ]);
    }

    public function approve(Request $request, Requisition $requisition, ApprovalService $approvals)
    {
        $this->decide($request, $requisition, $approvals, 'approved');

        return back()->with('status', 'Requisition approved.');
    }

    public function reject(Request $request, Requisition $requisition, ApprovalService $approvals)
    {
        $this->decide($request, $requisition, $approvals, 'rejected');

        return back()->with('status', 'Requisition rejected.');
    }

    public function cancel(Request $request, Requisition $requisition)
    {
        abort_unless($requisition->requested_by === $request->user()->id, 403);
        abort_unless($requisition->status === 'submitted', 422, 'Only submitted requisitions can be cancelled.');

        $requisition->approvals()->where('status', 'pending')->update(['status' => 'cancelled']);
        $requisition->update(['status' => 'cancelled']);

        return back()->with('status', 'Requisition cancelled.');
    }

    protected function decide(Request $request, Requisition $requisition, ApprovalService $approvals, string $decision): void
    {
        abort_unless($requisition->status === 'submitted', 422, 'This requisition has already been decided.');

        $data = $request->validate([
            'review_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $approval = $requisition->currentApproval();

        if ($approval) {
            $decision === 'approved'
                ? $approvals->approve($approval, $request->user(), $data['review_notes'] ?? null)
                : $approvals->reject($approval, $request->user(), $data['review_notes'] ?? null);
        }

        $requisition->update([
            'status' => $decision,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_notes' => $data['review_notes'] ?? null,
        ]);

        // An approved requisition becomes an expenditure record so it flows
        // straight into the finance ledger. Guard against duplicates.
        if ($decision === 'approved' && ! Expense::where('requisition_id', $requisition->id)->exists()) {
            Expense::create([
                'reference_no' => MonthlyReferenceNumber::make(Expense::class, 'reference_no', 'EX'),
                'expense_category_id' => null,
                'matter_id' => $requisition->matter_id,
                'requisition_id' => $requisition->id,
                'recorded_by' => $request->user()->id,
                'payee' => $requisition->requester?->name,
                'description' => $requisition->purpose,
                'amount' => $requisition->amount,
                'payment_source' => 'bank',
                'spent_on' => now()->toDateString(),
                'notes' => 'Auto-generated from requisition '.$requisition->reference_no,
            ]);
        }
    }
}
