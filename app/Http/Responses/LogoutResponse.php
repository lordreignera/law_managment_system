<?php

namespace App\Http\Responses;

use App\Models\AttendanceRecord;
use App\Services\AttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;
use Laravel\Fortify\Fortify;

class LogoutResponse implements LogoutResponseContract
{
    public function toResponse($request): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $recordId = $request->session()->pull('attendance_record_id');

        if ($user && $user->staffProfile?->employment_status === 'active') {
            app(AttendanceService::class)->checkOut($user, $request, 'logout');
        } elseif ($recordId) {
            $record = AttendanceRecord::find($recordId);
            if ($record && ! $record->checked_out_at) {
                $checkedOutAt = now();
                $record->update([
                    'checked_out_at' => $checkedOutAt,
                    'check_out_ip' => $request->ip(),
                    'check_out_device' => substr((string) ($request->header('X-Device-Name') ?: $request->userAgent()), 0, 191),
                    'check_out_user_agent' => $request->userAgent(),
                    'source' => 'logout',
                    'status' => app(AttendanceService::class)->statusFor($record->checked_in_at, $checkedOutAt),
                ]);
            }
        }

        if ($request->wantsJson()) {
            return new JsonResponse('', 204);
        }

        if ($request->boolean('client_portal_logout')) {
            return redirect()->route('client.portal');
        }

        return redirect(Fortify::redirects('logout', '/'));
    }
}
