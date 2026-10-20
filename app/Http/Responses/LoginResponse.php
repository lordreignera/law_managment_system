<?php

namespace App\Http\Responses;

use App\Services\AttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $resumingVerification = $request->session()->pull('verification.resume_after_login', false);

        if ($user?->isClientAccount()) {
            if (! $user->hasVerifiedEmail()) {
                return redirect()->intended(route('verification.notice'));
            }

            $portalAccount = $user->clientPortalAccount;

            $portalAccount?->update([
                'last_login_at' => now(),
                'verified_at' => $portalAccount?->verified_at,
            ]);

            return redirect()->intended(route('client.dashboard'));
        }

        if ($this->staffIsPendingApproval($user)) {
            return $this->pendingApprovalResponse($request);
        }

        if ($user?->staffProfile?->employment_status === 'active') {
            $record = app(AttendanceService::class)->checkIn($user, $request, 'login');
            $request->session()->put('attendance_record_id', $record->id);
        }

        return $resumingVerification
            ? redirect()->intended(route('verification.notice'))
            : redirect($this->staffHome($user));
    }

    private function staffIsPendingApproval($user): bool
    {
        return $user?->staffProfile?->employment_status === 'pending';
    }

    private function pendingApprovalResponse(Request $request): RedirectResponse
    {
        Auth::guard(config('fortify.guard'))->logout();

        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('login')
            ->with('approval_pending', true)
            ->with('status', 'Your account is still waiting for administrator approval. Please wait for the approval email or contact the administrator.')
            ->withInput($request->only('email'));
    }

    private function staffHome($user): string
    {
        $roleDashboards = [
            'Litigation Officer' => 'litigation.dashboard',
            'Senior Partner' => 'matters.dashboard',
            'Advocate' => 'matters.dashboard',
            'Paralegal' => 'matters.dashboard',
            'Accountant' => 'finance.dashboard',
            'HR Manager' => 'hr.dashboard',
            'Recoveries Manager' => 'recoveries.dashboard',
            'Recovery Officer' => 'recoveries.mine',
            'Securities Manager' => 'securities.dashboard',
        ];

        foreach ($roleDashboards as $role => $route) {
            if ($user?->hasRole($role) && $user->can($route)) {
                return route($route, absolute: false);
            }
        }

        return route('dashboard', absolute: false);
    }
}
