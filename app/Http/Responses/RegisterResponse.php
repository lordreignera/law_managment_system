<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;

class RegisterResponse implements RegisterResponseContract
{
    public function toResponse($request): JsonResponse|RedirectResponse
    {
        $this->logoutRegisteredUser($request);

        if ($request->wantsJson()) {
            return new JsonResponse('', 201);
        }

        return redirect()
            ->route('login')
            ->with('status', 'Your access request has been submitted. Please wait for an administrator to review and approve your account.');
    }

    private function logoutRegisteredUser(Request $request): void
    {
        Auth::guard(config('fortify.guard'))->logout();

        $request->session()->regenerate();
        $request->session()->regenerateToken();
    }
}
