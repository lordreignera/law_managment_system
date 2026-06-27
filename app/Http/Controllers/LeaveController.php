<?php

namespace App\Http\Controllers;

use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\ApprovalService;
use App\Support\MonthlyReferenceNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class LeaveController extends Controller
{
    public function index(Request $request)
    {
        $canApprove = $request->user()->can('leave.approve');

        $leave = LeaveRequest::with(['user', 'leaveType', 'reviewer'])
            ->when(! $canApprove, fn ($query) => $query->where('user_id', $request->user()->id))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search) {
                    $query
                        ->where('leave_no', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($query) => $query->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('leave_type_id'), fn ($query) => $query->where('leave_type_id', $request->integer('leave_type_id')))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $scope = LeaveRequest::query()
            ->when(! $canApprove, fn ($query) => $query->where('user_id', $request->user()->id));

        $summary = [
            'Total Requests' => (clone $scope)->count(),
            'Pending' => (clone $scope)->where('status', 'submitted')->count(),
            'Approved' => (clone $scope)->where('status', 'approved')->count(),
            'Rejected' => (clone $scope)->where('status', 'rejected')->count(),
            'Cancelled' => (clone $scope)->where('status', 'cancelled')->count(),
        ];

        return view('modules.leave.index', [
            'leave' => $leave,
            'filters' => $request->only(['search', 'status', 'leave_type_id']),
            'statuses' => LeaveRequest::STATUSES,
            'leaveTypes' => LeaveType::orderBy('name')->get(),
            'canApprove' => $canApprove,
            'summary' => $summary,
        ]);
    }

    public function create()
    {
        return view('modules.leave.create', [
            'leaveNumber' => MonthlyReferenceNumber::make(LeaveRequest::class, 'leave_no', 'LV'),
            'leaveTypes' => LeaveType::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, ApprovalService $approvals)
    {
        $data = $request->validate([
            'leave_type_id' => ['required', 'exists:leave_types,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'attachment' => ['nullable', 'file', 'max:5120'],
        ]);

        $days = Carbon::parse($data['start_date'])->diffInDays(Carbon::parse($data['end_date'])) + 1;

        $leave = DB::transaction(function () use ($data, $days, $request, $approvals) {
            $leave = LeaveRequest::create([
                'leave_no' => MonthlyReferenceNumber::make(LeaveRequest::class, 'leave_no', 'LV'),
                'user_id' => $request->user()->id,
                'leave_type_id' => $data['leave_type_id'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'days' => $days,
                'reason' => $data['reason'] ?? null,
                'status' => 'submitted',
            ]);

            if ($request->hasFile('attachment')) {
                $leave->addAttachment($request->file('attachment'), ['category' => 'leave-support']);
            }

            $approvals->submit($leave, $request->user());

            return $leave;
        });

        return redirect()
            ->route('leave.show', $leave)
            ->with('status', 'Leave request '.$leave->leave_no.' submitted for approval.');
    }

    public function show(Request $request, LeaveRequest $leave)
    {
        abort_unless(
            $leave->user_id === $request->user()->id || $request->user()->can('leave.approve'),
            403
        );

        return view('modules.leave.show', [
            'leave' => $leave->load(['user', 'leaveType', 'reviewer', 'attachments', 'approvals.approver']),
            'canApprove' => $request->user()->can('leave.approve'),
        ]);
    }

    public function approve(Request $request, LeaveRequest $leave, ApprovalService $approvals)
    {
        $this->decide($request, $leave, $approvals, 'approved');

        return back()->with('status', 'Leave request approved.');
    }

    public function reject(Request $request, LeaveRequest $leave, ApprovalService $approvals)
    {
        $this->decide($request, $leave, $approvals, 'rejected');

        return back()->with('status', 'Leave request rejected.');
    }

    public function cancel(Request $request, LeaveRequest $leave)
    {
        abort_unless($leave->user_id === $request->user()->id, 403);
        abort_unless($leave->status === 'submitted', 422, 'Only submitted requests can be cancelled.');

        $leave->approvals()->where('status', 'pending')->update(['status' => 'cancelled']);
        $leave->update(['status' => 'cancelled']);

        return back()->with('status', 'Leave request cancelled.');
    }

    protected function decide(Request $request, LeaveRequest $leave, ApprovalService $approvals, string $decision): void
    {
        abort_unless($leave->status === 'submitted', 422, 'This request has already been decided.');

        $data = $request->validate([
            'review_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $approval = $leave->currentApproval();

        if ($approval) {
            $decision === 'approved'
                ? $approvals->approve($approval, $request->user(), $data['review_notes'] ?? null)
                : $approvals->reject($approval, $request->user(), $data['review_notes'] ?? null);
        }

        $leave->update([
            'status' => $decision,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_notes' => $data['review_notes'] ?? null,
        ]);
    }
}
