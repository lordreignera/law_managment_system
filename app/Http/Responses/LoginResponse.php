<?php

namespace App\Http\Responses;

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

        if ($user?->isClientAccount()) {
            $portalAccount = $user->clientPortalAccount;

            if (! $user->hasVerifiedEmail()) {
                $user->markEmailAsVerified();
            }

            $portalAccount?->update([
                'last_login_at' => now(),
                'verified_at' => $portalAccount?->verified_at ?: now(),
            ]);

            return redirect()->intended(route('client.dashboard'));
        }

        if ($this->staffIsPendingApproval($user)) {
            return $this->pendingApprovalResponse($request);
        }

        return redirect($this->staffHome($user));
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
            'Securities Manager' => 'land-titles.dashboard',
        ];

        foreach ($roleDashboards as $role => $route) {
            if ($user?->hasRole($role) && $user->can($route)) {
                return route($route, absolute: false);
            }
        }

        return route('dashboard', absolute: false);
    }
}
