<?php

namespace App\Http\Controllers;

use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Notifications\LeaveApprovalRequested;
use App\Notifications\LeaveRequestStatusChanged;
use App\Services\ApprovalService;
use App\Support\MonthlyReferenceNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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

        $annualLeavePlan = null;
        $leaveBalances = collect();

        if (! $canApprove) {
            $scheduledDays = LeaveRequest::annualScheduledDaysFor($request->user(), now()->year);
            $requiredDays = LeaveRequest::annualEntitlementForUser($request->user(), now()->year);
            $annualLeavePlan = [
                'year' => now()->year,
                'scheduled_days' => $scheduledDays,
                'required_days' => $requiredDays,
                'remaining_days' => max(0, $requiredDays - $scheduledDays),
            ];

            $leaveBalances = LeaveType::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
                ->map(function (LeaveType $type) use ($request) {
                    $used = LeaveRequest::scheduledDaysFor($request->user(), $type->id, now()->year);
                    $entitlement = LeaveRequest::entitlementFor($request->user(), $type, now()->year);

                    return [
                        'type' => $type,
                        'entitlement' => $entitlement,
                        'used' => $used,
                        'remaining' => max(0, $entitlement - $used),
                    ];
                });
        }

        return view('modules.leave.index', [
            'leave' => $leave,
            'filters' => $request->only(['search', 'status', 'leave_type_id']),
            'statuses' => LeaveRequest::STATUSES,
            'leaveTypes' => LeaveType::where('is_active', true)->orderBy('name')->get(),
            'canApprove' => $canApprove,
            'summary' => $summary,
            'annualLeavePlan' => $annualLeavePlan,
            'leaveBalances' => $leaveBalances,
        ]);
    }

    public function create()
    {
        return view('modules.leave.create', [
            'leaveNumber' => MonthlyReferenceNumber::make(LeaveRequest::class, 'leave_no', 'LV'),
            'leaveTypes' => LeaveType::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, ApprovalService $approvals)
    {
        $data = $request->validate([
            'leave_type_id' => ['required', Rule::exists('leave_types', 'id')->where('is_active', true)],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'attachment' => ['nullable', 'file', 'max:5120'],
        ]);

        $leaveType = LeaveType::findOrFail($data['leave_type_id']);
        if ($leaveType->requires_attachment && ! $request->hasFile('attachment')) {
            throw ValidationException::withMessages(['attachment' => 'A supporting document is required for this leave type.']);
        }

        $this->ensureNoConflict($request->user(), $data['start_date'], $data['end_date']);
        $this->ensureWithinBalance($request->user(), $leaveType, $data['start_date'], $data['end_date']);

        $approverIds = $this->approvalChain($request->user());
        $days = Carbon::parse($data['start_date'])->diffInDays(Carbon::parse($data['end_date'])) + 1;

        $leave = DB::transaction(function () use ($data, $days, $request, $approvals, $approverIds) {
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

            $approvals->submit($leave, $request->user(), $approverIds);

            return $leave;
        });

        $this->notifyCurrentApprover($leave->fresh(['approvals.approver', 'user', 'leaveType']));

        return redirect()
            ->route('leave.show', $leave)
            ->with('status', 'Leave request '.$leave->leave_no.' submitted to the department manager.');
    }

    public function show(Request $request, LeaveRequest $leave)
    {
        abort_unless(
            $leave->user_id === $request->user()->id || $request->user()->can('leave.approve'),
            403
        );

        $leave->load(['user', 'leaveType', 'reviewer', 'attachments', 'approvals.approver']);

        return view('modules.leave.show', [
            'leave' => $leave,
            'canApprove' => $this->canActOnCurrentApproval($request->user(), $leave),
            'currentApproval' => $leave->currentApproval(),
        ]);
    }

    public function rescheduleEdit(Request $request, LeaveRequest $leave)
    {
        abort_unless($leave->user_id === $request->user()->id, 403);
        abort_unless($leave->status === 'rejected', 422, 'Only rejected requests can be rescheduled.');

        return view('modules.leave.reschedule', [
            'leave' => $leave->load('leaveType'),
            'leaveTypes' => LeaveType::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function reschedule(Request $request, LeaveRequest $leave, ApprovalService $approvals)
    {
        abort_unless($leave->user_id === $request->user()->id, 403);
        abort_unless($leave->status === 'rejected', 422, 'Only rejected requests can be rescheduled.');

        $data = $request->validate([
            'leave_type_id' => ['required', Rule::exists('leave_types', 'id')->where('is_active', true)],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'attachment' => ['nullable', 'file', 'max:5120'],
        ]);

        $leaveType = LeaveType::findOrFail($data['leave_type_id']);
        if ($leaveType->requires_attachment && ! $request->hasFile('attachment')) {
            throw ValidationException::withMessages(['attachment' => 'A supporting document is required for this leave type.']);
        }

        $this->ensureNoConflict($request->user(), $data['start_date'], $data['end_date'], $leave);
        $this->ensureWithinBalance($request->user(), $leaveType, $data['start_date'], $data['end_date']);

        $approverIds = $this->approvalChain($request->user());
        $days = Carbon::parse($data['start_date'])->diffInDays(Carbon::parse($data['end_date'])) + 1;

        $leave = DB::transaction(function () use ($data, $days, $request, $leave, $approvals, $approverIds) {
            $leave->update([
                'leave_type_id' => $data['leave_type_id'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'days' => $days,
                'reason' => $data['reason'] ?? null,
                'status' => 'submitted',
                'resubmission_count' => $leave->resubmission_count + 1,
                'resubmitted_at' => now(),
                'reviewed_by' => null,
                'reviewed_at' => null,
                'review_notes' => null,
            ]);

            if ($request->hasFile('attachment')) {
                $leave->addAttachment($request->file('attachment'), ['category' => 'leave-support']);
            }

            $approvals->submit($leave, $request->user(), $approverIds);

            return $leave;
        });

        $this->notifyCurrentApprover($leave->fresh(['approvals.approver', 'user', 'leaveType']));

        return redirect()
            ->route('leave.show', $leave)
            ->with('status', 'Leave request rescheduled and sent back to the department manager.');
    }

    public function approve(Request $request, LeaveRequest $leave, ApprovalService $approvals)
    {
        $this->decide($request, $leave, $approvals, 'approved');

        return back()->with('status', 'Leave approval recorded.');
    }

    public function reject(Request $request, LeaveRequest $leave, ApprovalService $approvals)
    {
        $this->decide($request, $leave, $approvals, 'rejected');

        return back()->with('status', 'Leave request rejected. The employee can reschedule it.');
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
        abort_unless($approval, 422, 'This request has no pending approval stage.');
        abort_unless(
            $approval->approver_id === null || $approval->approver_id === $request->user()->id,
            403,
            'This request is waiting for another reviewer.'
        );

        $decision === 'approved'
            ? $approvals->approve($approval, $request->user(), $data['review_notes'] ?? null)
            : $approvals->reject($approval, $request->user(), $data['review_notes'] ?? null);

        $leave->refresh();
        $nextApproval = $leave->currentApproval();
        $finalStatus = $decision === 'rejected'
            ? 'rejected'
            : ($nextApproval ? 'submitted' : 'approved');

        $updates = ['status' => $finalStatus];

        if ($decision === 'rejected' || ! $nextApproval) {
            $updates += [
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'review_notes' => $data['review_notes'] ?? null,
            ];
        }

        $leave->update($updates);
        $leave->load(['user', 'leaveType']);

        $leave->user?->notify(new LeaveRequestStatusChanged(
            $leave,
            $decision,
            $nextApproval ? LeaveRequest::approvalStageLabel($nextApproval->level) : null,
            $request->user()->name,
        ));

        if ($nextApproval?->approver) {
            $nextApproval->approver->notify(new LeaveApprovalRequested(
                $leave,
                LeaveRequest::approvalStageLabel($nextApproval->level),
            ));
        }
    }

    protected function canActOnCurrentApproval(User $user, LeaveRequest $leave): bool
    {
        $approval = $leave->currentApproval();

        return $user->can('leave.approve')
            && $approval
            && ($approval->approver_id === null || $approval->approver_id === $user->id);
    }

    protected function ensureNoConflict(User $user, string $startDate, string $endDate, ?LeaveRequest $ignore = null): void
    {
        $conflict = LeaveRequest::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['submitted', 'approved'])
            ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->id))
            ->whereDate('start_date', '<=', $endDate)
            ->whereDate('end_date', '>=', $startDate)
            ->exists();

        if ($conflict) {
            throw ValidationException::withMessages([
                'start_date' => 'These dates overlap another submitted or approved leave request.',
            ]);
        }
    }

    protected function ensureWithinBalance(User $user, LeaveType $leaveType, string $startDate, string $endDate): void
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->startOfDay();

        for ($year = $start->year; $year <= $end->year; $year++) {
            $yearStart = Carbon::create($year, 1, 1)->startOfDay();
            $yearEnd = Carbon::create($year, 12, 31)->startOfDay();
            $periodStart = $start->greaterThan($yearStart) ? $start : $yearStart;
            $periodEnd = $end->lessThan($yearEnd) ? $end : $yearEnd;
            $requestedDays = $periodStart->diffInDays($periodEnd) + 1;
            $scheduledDays = LeaveRequest::scheduledDaysFor($user, $leaveType->id, $year);
            $entitlement = LeaveRequest::entitlementFor($user, $leaveType, $year);

            if ($scheduledDays + $requestedDays > $entitlement) {
                $remaining = max(0, $entitlement - $scheduledDays);

                throw ValidationException::withMessages([
                    'start_date' => sprintf(
                        'This request exceeds your available %s balance for %d. You have %s day%s remaining, but requested %s.',
                        $leaveType->name,
                        $year,
                        rtrim(rtrim(number_format($remaining, 1), '0'), '.'),
                        $remaining === 1.0 ? '' : 's',
                        rtrim(rtrim(number_format($requestedDays, 1), '0'), '.'),
                    ),
                ]);
            }
        }
    }

    protected function notifyCurrentApprover(LeaveRequest $leave): void
    {
        $approval = $leave->currentApproval();

        if ($approval?->approver) {
            $approval->approver->notify(new LeaveApprovalRequested(
                $leave,
                LeaveRequest::approvalStageLabel($approval->level),
            ));
        }
    }

    /**
     * Resolve three distinct, active reviewers so every new request follows
     * the same approval policy regardless of who happens to open it first.
     *
     * @return array<int, int>
     */
    protected function approvalChain(User $employee): array
    {
        $employee->loadMissing(['manager.staffProfile', 'staffProfile']);
        $departmentId = $employee->department_id ?: $employee->staffProfile?->department_id;

        $manager = $employee->manager;

        if (! $manager && $departmentId) {
            $manager = User::query()
                ->with('staffProfile')
                ->whereKeyNot($employee->id)
                ->whereHas('staffProfile', fn ($query) => $query->where('employment_status', 'active'))
                ->where(function ($query) use ($departmentId) {
                    $query->where('department_id', $departmentId)
                        ->orWhereHas('staffProfile', fn ($profile) => $profile->where('department_id', $departmentId));
                })
                ->where(function ($query) {
                    $query
                        ->whereHas('roles', fn ($role) => $role->whereIn('name', ['Department Manager', 'Managing Partner', 'Senior Partner']))
                        ->orWhereHas('staffProfile', fn ($profile) => $profile->where(function ($title) {
                            $title->where('job_title', 'like', '%Department Manager%')
                                ->orWhere('job_title', 'like', '%Head of%');
                        }));
                })
                ->orderBy('name')
                ->first();
        }

        $usedApproverIds = $manager ? [$manager->id] : [];
        $hrApprover = $this->activeRoleUser($employee, ['HR Manager', 'Administrator'], $usedApproverIds);

        if (! $hrApprover) {
            $hrApprover = $this->activeRoleUser($employee, ['Super Admin'], $usedApproverIds);
        }

        if ($hrApprover) {
            $usedApproverIds[] = $hrApprover->id;
        }

        $finalApprover = $this->activeRoleUser($employee, ['Super Admin', 'CEO'], $usedApproverIds);

        if (! $finalApprover) {
            $finalApprover = $this->activeRoleUser($employee, ['Administrator', 'Managing Partner'], $usedApproverIds);
        }

        if (! $finalApprover) {
            $finalApprover = User::query()
                ->with('staffProfile')
                ->whereKeyNot($employee->id)
                ->when($usedApproverIds !== [], fn ($query) => $query->whereNotIn('id', $usedApproverIds))
                ->whereHas('staffProfile', fn ($query) => $query->where('employment_status', 'active'))
                ->whereHas('staffProfile', fn ($query) => $query->where('job_title', 'like', '%Chief Executive%')->orWhere('job_title', 'like', '%CEO%'))
                ->orderBy('name')
                ->first();
        }

        $stages = [
            'Department Manager' => $manager,
            'HR / Administrator' => $hrApprover,
            'Super Admin / CEO' => $finalApprover,
        ];
        $approverIds = [];
        $missing = [];

        foreach ($stages as $label => $approver) {
            if (! $approver || $approver->staffProfile?->employment_status !== 'active' || in_array($approver->id, $approverIds, true)) {
                $missing[] = $label;

                continue;
            }

            $approverIds[] = $approver->id;
        }

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'leave' => 'Leave approval is not fully configured. Please assign active reviewers for: '.implode(', ', $missing).'.',
            ]);
        }

        return $approverIds;
    }

    protected function activeRoleUser(User $employee, array $roles, array $excludeIds = []): ?User
    {
        return User::query()
            ->with('staffProfile')
            ->whereKeyNot($employee->id)
            ->when($excludeIds !== [], fn ($query) => $query->whereNotIn('id', $excludeIds))
            ->whereHas('staffProfile', fn ($query) => $query->where('employment_status', 'active'))
            ->whereHas('roles', fn ($query) => $query->whereIn('name', $roles))
            ->orderBy('name')
            ->first();
    }
}
