<?php

namespace App\Models;

use App\Models\Concerns\Approvable;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class LeaveRequest extends Model
{
    use Approvable;
    use Auditable;
    use HasAttachments;
    use HasFactory;
    use SoftDeletes;

    public const STATUSES = [
        'submitted' => 'Submitted',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'cancelled' => 'Cancelled',
    ];

    public const ANNUAL_LEAVE_DAYS = 21;

    protected $guarded = [];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'days' => 'float',
        'reviewed_at' => 'datetime',
        'resubmitted_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public static function approvalStageLabel(int $level): string
    {
        return match ($level) {
            1 => 'Department Manager',
            2 => 'HR / Administrator',
            default => 'Super Admin / CEO',
        };
    }

    public static function annualScheduledDaysFor(User $user, int $year): float
    {
        $annualLeaveTypeId = LeaveType::query()
            ->where('name', 'Annual Leave')
            ->value('id');

        if (! $annualLeaveTypeId) {
            return 0.0;
        }

        return static::scheduledDaysFor($user, $annualLeaveTypeId, $year);
    }

    public static function scheduledDaysFor(User $user, int $leaveTypeId, int $year): float
    {
        $yearStart = Carbon::create($year, 1, 1)->startOfDay();
        $yearEnd = Carbon::create($year, 12, 31)->endOfDay();

        return (float) static::query()
            ->where('user_id', $user->id)
            ->where('leave_type_id', $leaveTypeId)
            ->whereIn('status', ['submitted', 'approved'])
            ->whereDate('start_date', '<=', $yearEnd)
            ->whereDate('end_date', '>=', $yearStart)
            ->get(['start_date', 'end_date'])
            ->sum(function (self $leave) use ($yearStart, $yearEnd): int {
                $start = $leave->start_date->greaterThan($yearStart) ? $leave->start_date : $yearStart;
                $end = $leave->end_date->lessThan($yearEnd) ? $leave->end_date : $yearEnd;

                return $start->greaterThan($end) ? 0 : $start->diffInDays($end) + 1;
            });
    }

    /**
     * Return the entitlement available to a user for a leave type in a year.
     *
     * Carry-forward uses the prior year's unused configured entitlement. The
     * leave type is configuration data, so this remains deterministic even
     * when a fresh installation has no prior leave records.
     */
    public static function entitlementFor(User $user, LeaveType $leaveType, int $year): float
    {
        $entitlement = max(0, (float) $leaveType->annual_entitlement_days);

        if (! $leaveType->carry_forward || $year <= 1) {
            return $entitlement;
        }

        $priorYearUsed = static::scheduledDaysFor($user, $leaveType->id, $year - 1);

        return $entitlement + max(0, $entitlement - $priorYearUsed);
    }

    public static function annualEntitlementForUser(User $user, int $year): float
    {
        $annualLeaveType = LeaveType::query()->where('name', 'Annual Leave')->first();

        return $annualLeaveType
            ? static::entitlementFor($user, $annualLeaveType, $year)
            : self::ANNUAL_LEAVE_DAYS;
    }

    public static function annualEntitlementFor(int $year): float
    {
        return (float) (LeaveType::query()
            ->where('name', 'Annual Leave')
            ->value('annual_entitlement_days') ?? self::ANNUAL_LEAVE_DAYS);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? str($this->status)->headline()->toString();
    }
}
