<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AttendanceService
{
    public function checkIn(User $user, Request $request, string $source = 'self'): AttendanceRecord
    {
        $record = $this->recordFor($user, today());

        if (! $record->checked_in_at) {
            $record->forceFill([
                'checked_in_at' => now(),
                'check_in_ip' => $request->ip(),
                'check_in_device' => $this->device($request),
                'check_in_user_agent' => $request->userAgent(),
                'source' => $source,
            ])->save();
        }

        $record->forceFill(['status' => $this->statusFor($record->checked_in_at, $record->checked_out_at)])->save();

        return $record->fresh();
    }

    public function checkOut(User $user, Request $request, string $source = 'self'): AttendanceRecord
    {
        $record = AttendanceRecord::query()
            ->where('user_id', $user->id)
            ->whereDate('attendance_date', today())
            ->first();

        if (! $record) {
            return new AttendanceRecord([
                'user_id' => $user->id,
                'attendance_date' => today()->toDateString(),
                'status' => 'incomplete',
                'source' => 'system',
            ]);
        }

        if (! $record->checked_in_at) {
            return $record->fresh();
        }

        if (! $record->checked_out_at) {
            $record->forceFill([
                'checked_out_at' => now(),
                'check_out_ip' => $request->ip(),
                'check_out_device' => $this->device($request),
                'check_out_user_agent' => $request->userAgent(),
                'source' => $source,
            ])->save();
        }

        $record->forceFill(['status' => $this->statusFor($record->checked_in_at, $record->checked_out_at)])->save();

        return $record->fresh();
    }

    public function recordFor(User $user, Carbon $date): AttendanceRecord
    {
        $record = AttendanceRecord::query()
            ->where('user_id', $user->id)
            ->whereDate('attendance_date', $date->toDateString())
            ->first();

        return $record ?: AttendanceRecord::create([
            'user_id' => $user->id,
            'attendance_date' => $date->toDateString(),
            'status' => 'incomplete',
            'source' => 'system',
        ]);
    }

    public function statusFor($checkedInAt, $checkedOutAt): string
    {
        if (! $checkedInAt || ! $checkedOutAt) {
            return $checkedInAt && Carbon::parse($checkedInAt)->greaterThan($this->startTime()->addMinutes($this->graceMinutes())) ? 'late' : 'incomplete';
        }

        return Carbon::parse($checkedInAt)->greaterThan($this->startTime()->addMinutes($this->graceMinutes())) ? 'late' : 'present';
    }

    private function startTime(): Carbon
    {
        return today()->setTimeFromTimeString((string) config('attendance.start_time', '08:30'));
    }

    private function graceMinutes(): int
    {
        return (int) config('attendance.grace_minutes', 15);
    }

    private function device(Request $request): string
    {
        return substr((string) ($request->header('X-Device-Name') ?: $request->userAgent()), 0, 191);
    }
}
