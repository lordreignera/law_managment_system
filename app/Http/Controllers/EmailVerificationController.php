<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AttendanceService;
use App\Services\EmailVerificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Laravel\Fortify\Http\Controllers\EmailVerificationNotificationController;

class EmailVerificationController extends EmailVerificationNotificationController
{
    public function __construct(private EmailVerificationService $verification) {}

    public function store(Request $request)
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            $destination = route($user->isClientAccount() ? 'client.dashboard' : 'dashboard');

            return $request->expectsJson()
                ? response()->json(['verified' => true, 'redirect' => $destination])
                : redirect($destination);
        }

        $result = $this->verification->send($user);

        if ($request->expectsJson()) {
            $response = response()->json($result, match ($result['status']) {
                'wait' => 429, 'failed' => 503, default => 200,
            });

            return $result['retry_after'] > 0 ? $response->header('Retry-After', $result['retry_after']) : $response;
        }

        return back()->with('status', match ($result['status']) {
            'wait' => 'verification-wait', 'failed' => 'verification-delivery-failed', default => 'verification-link-sent',
        });
    }

    public function linkError(Request $request)
    {
        if ((string) $request->user()->getKey() !== (string) $request->route('id')) {
            $canResumeVerification = URL::hasCorrectSignature($request);
            $request->session()->forget('verification.pending');

            if ($canResumeVerification) {
                $id = (string) $request->route('id');
                $request->session()->put('verification.pending', [
                    'parameters' => array_merge($request->query(), ['id' => $id, 'hash' => $request->route('hash')]),
                    'client_portal' => ctype_digit($id) && User::query()->whereKey($id)->where('account_type', 'client')->exists(),
                ]);
            }

            return response()->view('auth.verification-account-mismatch', compact('canResumeVerification'), 403)
                ->header('Cache-Control', 'private, no-store');
        }

        return response()->view('auth.verify-email', ['verificationExpired' => true], 403)
            ->header('Cache-Control', 'private, no-store');
    }

    public function switchAccount(Request $request)
    {
        $pending = $request->session()->get('verification.pending');

        if (! $pending) {
            return redirect()->route('verification.notice');
        }

        $verificationUrl = route('verification.verify', $pending['parameters'], false);
        $loginRoute = $pending['client_portal'] ? 'client.login' : 'login';
        $user = $request->user();

        if ($user->staffProfile?->employment_status === 'active') {
            app(AttendanceService::class)->checkOut($user, $request, 'verification-account-switch');
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $request->session()->put('url.intended', $verificationUrl);
        $request->session()->put('verification.resume_after_login', true);

        return redirect()->route($loginRoute)->with('status', 'Sign in with the account that received the verification email to continue.');
    }
}
