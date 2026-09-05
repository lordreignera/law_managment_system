<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
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

        return redirect($this->staffHome($user));
    }

    private function staffHome($user): string
    {
        $roleDashboards = [
            'Litigation Officer' => 'litigation.dashboard',
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
