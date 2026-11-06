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
        $isHeartbeat = $request->routeIs('session.heartbeat');
        $effectiveActivity = $now;

        if ($isHeartbeat) {
            $activityAge = filter_var($request->input('activity_age_ms'), FILTER_VALIDATE_INT);

            // A heartbeat confirms that the browser is still open; it is not
            // proof that the user is active. Preserve the browser's reported
            // activity time so a background tab cannot keep a session alive.
            if ($activityAge !== false && $activityAge >= 0 && $activityAge <= $timeoutSeconds * 1000) {
                $reportedActivity = $now - (int) ceil($activityAge / 1000);
                $effectiveActivity = max($reportedActivity, $lastActivity);
            } else {
                $effectiveActivity = $lastActivity;
            }
        }

        $idleDuration = $isHeartbeat && $effectiveActivity > $lastActivity
            ? $effectiveActivity - $lastActivity
            : $now - $lastActivity;

        if ($timeoutSeconds > 0 && $idleDuration >= $timeoutSeconds) {
            Auth::guard($guard)->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson() || $isHeartbeat) {
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

        if (! $isHeartbeat) {
            $request->session()->put(self::LAST_ACTIVITY_KEY, $now);
        } elseif ($effectiveActivity > $lastActivity) {
            $request->session()->put(self::LAST_ACTIVITY_KEY, $effectiveActivity);
        }

        return $next($request);
    }
}
