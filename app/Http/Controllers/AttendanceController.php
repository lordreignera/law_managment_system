<?php

namespace App\Http\Controllers;

use App\Models\AttendanceCorrectionRequest;
use App\Models\AttendanceRecord;
use App\Models\LeaveRequest;
use App\Models\PublicHoliday;
use App\Models\User;
use App\Services\ApprovalService;
use App\Services\AttendanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $date = $request->filled('date') ? Carbon::parse($request->string('date')->toString()) : today();
        $canReport = $user->can('attendance.reports');
        $scope = $canReport ? $this->staffScope($user) : User::query()->whereKey($user->id);
        $staff = $scope->with(['staffProfile', 'branch', 'department'])->get();
        $records = AttendanceRecord::with('user')->whereDate('attendance_date', $date)->whereIn('user_id', $staff->pluck('id'))->get()->keyBy('user_id');
        $onLeave = LeaveRequest::whereIn('user_id', $staff->pluck('id'))
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->pluck('user_id')
            ->all();

        $rows = $staff->map(function (User $staffMember) use ($records, $onLeave, $date) {
            $record = $records->get($staffMember->id);
            $status = in_array($staffMember->id, $onLeave, true) ? 'on_leave' : ($record ? $record->status : ($date->isFuture() ? 'not_due' : 'absent'));

            return compact('staffMember', 'record', 'status');
        });

        $corrections = AttendanceCorrectionRequest::with(['user', 'requester', 'approvals.approver'])
            ->when(! $canReport, fn ($query) => $query->where('user_id', $user->id))
            ->when($canReport, fn ($query) => $query->where('status', 'submitted'))
            ->latest()
            ->limit(20)
            ->get();

        return view('modules.attendance.index', [
            'date' => $date,
            'rows' => $rows,
            'canReport' => $canReport,
            'todayRecord' => AttendanceRecord::where('user_id', $user->id)->whereDate('attendance_date', today())->first(),
            'corrections' => $corrections,
            'summary' => [
                'Present' => $rows->whereIn('status', ['present', 'corrected'])->count(),
                'Late' => $rows->where('status', 'late')->count(),
                'Absent' => $rows->where('status', 'absent')->count(),
                'On Leave' => $rows->where('status', 'on_leave')->count(),
            ],
        ]);
    }

    public function checkIn(Request $request, AttendanceService $attendance)
    {
        $attendance->checkIn($request->user(), $request);

        return back()->with('status', 'Attendance check-in recorded.');
    }

    public function monthly(Request $request)
    {
        abort_unless($request->user()->can('attendance.reports'), 403);

        $month = $request->filled('month')
            ? Carbon::createFromFormat('Y-m', $request->string('month')->toString())->startOfMonth()
            : now()->startOfMonth();
        $rangeStart = $month->copy()->startOfMonth();
        $rangeEnd = $month->copy()->endOfMonth();
        $staff = $this->staffScope($request->user())->with(['staffProfile', 'branch', 'department'])->get();
        $staffIds = $staff->pluck('id');
        $records = AttendanceRecord::query()
            ->whereIn('user_id', $staffIds)
            ->whereDate('attendance_date', '>=', $rangeStart->toDateString())
            ->whereDate('attendance_date', '<=', $rangeEnd->toDateString())
            ->get()
            ->groupBy('user_id');
        $leave = LeaveRequest::query()
            ->whereIn('user_id', $staffIds)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $rangeEnd)
            ->whereDate('end_date', '>=', $rangeStart)
            ->get(['user_id', 'start_date', 'end_date'])
            ->groupBy('user_id');
        $holidays = PublicHoliday::forMonth($month->year, $month->month)->keys();
        $workDates = collect();

        for ($date = $rangeStart->copy(); $date->lte($rangeEnd); $date->addDay()) {
            if ($date->isWeekday() && ! $holidays->contains($date->toDateString())) {
                $workDates->push($date->copy());
            }
        }

        $rows = $staff->map(function (User $staffMember) use ($records, $leave, $workDates) {
            $memberRecords = $records->get($staffMember->id, collect())->keyBy(fn (AttendanceRecord $record) => $record->attendance_date->toDateString());
            $memberLeave = $leave->get($staffMember->id, collect());
            $totals = ['present' => 0, 'late' => 0, 'absent' => 0, 'on_leave' => 0, 'incomplete' => 0];

            foreach ($workDates as $date) {
                $onLeave = $memberLeave->contains(fn (LeaveRequest $request) => $request->start_date->lte($date) && $request->end_date->gte($date));
                if ($onLeave) {
                    $totals['on_leave']++;

                    continue;
                }

                $record = $memberRecords->get($date->toDateString());
                if (! $record || ! $record->checked_in_at) {
                    $totals['absent']++;

                    continue;
                }

                $totals[$record->status === 'late' ? 'late' : ($record->checked_out_at ? 'present' : 'incomplete')]++;
            }

            return ['staffMember' => $staffMember, 'totals' => $totals];
        });

        return view('modules.attendance.monthly', [
            'month' => $month,
            'rows' => $rows,
            'workdayCount' => $workDates->count(),
            'summary' => [
                'Staff' => $staff->count(),
                'Workdays' => $workDates->count(),
                'Present entries' => $rows->sum(fn ($row) => $row['totals']['present']),
                'Late entries' => $rows->sum(fn ($row) => $row['totals']['late']),
                'Absent entries' => $rows->sum(fn ($row) => $row['totals']['absent']),
            ],
        ]);
    }

    public function checkOut(Request $request, AttendanceService $attendance)
    {
        $record = $attendance->checkOut($request->user(), $request);

        if (! $record->checked_in_at) {
            return back()->withErrors(['attendance' => 'Check in before checking out.']);
        }

        return back()->with('status', 'Attendance check-out recorded.');
    }

    public function storeCorrection(Request $request, ApprovalService $approvals, AttendanceService $attendance)
    {
        $data = $request->validate([
            'attendance_date' => ['required', 'date', 'before_or_equal:today'],
            'requested_check_in_at' => ['nullable', 'date'],
            'requested_check_out_at' => ['nullable', 'date', 'after_or_equal:requested_check_in_at'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        if (empty($data['requested_check_in_at']) && empty($data['requested_check_out_at'])) {
            throw ValidationException::withMessages(['requested_check_in_at' => 'Provide a corrected check-in or check-out time.']);
        }

        $approver = $this->correctionApprover($request->user());
        abort_unless($approver, 422, 'Attendance correction approval is not configured for your manager.');
        $record = $attendance->recordFor($request->user(), Carbon::parse($data['attendance_date']));
        $correction = AttendanceCorrectionRequest::create([
            'attendance_record_id' => $record->id,
            'user_id' => $request->user()->id,
            'requested_by' => $request->user()->id,
            'attendance_date' => $data['attendance_date'],
            'requested_check_in_at' => $data['requested_check_in_at'] ?? null,
            'requested_check_out_at' => $data['requested_check_out_at'] ?? null,
            'reason' => $data['reason'],
            'status' => 'submitted',
        ]);
        $approvals->submit($correction, $request->user(), [$approver->id]);

        return back()->with('status', 'Attendance correction sent to your manager for approval.');
    }

    public function approveCorrection(Request $request, AttendanceCorrectionRequest $correction, ApprovalService $approvals)
    {
        $this->decideCorrection($request, $correction, $approvals, 'approved');

        return back()->with('status', 'Attendance correction approved.');
    }

    public function rejectCorrection(Request $request, AttendanceCorrectionRequest $correction, ApprovalService $approvals)
    {
        $this->decideCorrection($request, $correction, $approvals, 'rejected');

        return back()->with('status', 'Attendance correction rejected.');
    }

    protected function decideCorrection(Request $request, AttendanceCorrectionRequest $correction, ApprovalService $approvals, string $decision): void
    {
        abort_unless($correction->status === 'submitted', 422, 'This correction has already been decided.');
        $approval = $correction->currentApproval();
        abort_unless($approval && ($approval->approver_id === null || $approval->approver_id === $request->user()->id), 403);

        $notes = $request->validate(['review_notes' => ['nullable', 'string', 'max:2000']])['review_notes'] ?? null;
        $decision === 'approved' ? $approvals->approve($approval, $request->user(), $notes) : $approvals->reject($approval, $request->user(), $notes);
        $correction->update([
            'status' => $decision,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_notes' => $notes,
        ]);

        if ($decision === 'approved') {
            $record = $correction->attendanceRecord ?: AttendanceRecord::firstOrCreate([
                'user_id' => $correction->user_id,
                'attendance_date' => $correction->attendance_date->toDateString(),
            ]);
            $record->update([
                'checked_in_at' => $correction->requested_check_in_at,
                'checked_out_at' => $correction->requested_check_out_at,
                'source' => 'correction',
                'notes' => $correction->reason,
                'status' => 'corrected',
            ]);
        }
    }

    protected function staffScope(User $user)
    {
        return User::query()
            ->whereHas('staffProfile', fn ($query) => $query->where('employment_status', 'active'))
            ->when(! $user->canSeeAllBranches(), fn ($query) => $query->where('branch_id', $user->branch_id));
    }

    protected function correctionApprover(User $employee): ?User
    {
        $employee->loadMissing(['manager.staffProfile']);

        if ($employee->manager?->staffProfile?->employment_status === 'active') {
            return $employee->manager;
        }

        return User::query()
            ->whereKeyNot($employee->id)
            ->whereHas('staffProfile', fn ($query) => $query->where('employment_status', 'active'))
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['HR Manager', 'Administrator', 'Managing Partner']))
            ->orderBy('name')
            ->first();
    }
}
