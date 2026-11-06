<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureSessionIsFresh
{
    public const LAST_ACTIVITY_KEY = 'kfms.last_activity_at';

    public function handle(Request $request, Closure $next): Response
    {
        $guard = config('fortify.guard', config('auth.defaults.guard', 'web'));
        $user = Auth::guard($guard)->user();

        if (! $user) {
            return $next($request);
        }

        $timeoutMinutes = (int) config('session.idle_timeout', config('session.lifetime', 120));
        $timeoutSeconds = max(0, $timeoutMinutes * 60);
        $now = now()->timestamp;
        $lastActivity = (int) $request->session()->get(self::LAST_ACTIVITY_KEY, $now);

        if ($timeoutSeconds > 0 && ($now - $lastActivity) >= $timeoutSeconds) {
            Auth::guard($guard)->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Your session expired because of inactivity.',
                    'session_expired' => true,
                    'reason' => 'idle',
                    'login_url' => $request->is('client*') ? route('client.login') : route('login'),
                ], 401);
            }

            return redirect()->guest(route('login'))
                ->with('status', 'You were signed out after a period of inactivity.');
        }

        $request->session()->put(self::LAST_ACTIVITY_KEY, $now);

        return $next($request);
    }
}
