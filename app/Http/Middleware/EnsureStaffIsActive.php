<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaffIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403);
        }

        $status = $user->staffProfile()->value('employment_status');

        if ($status === null || $status === 'active') {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(403, 'Your account is not active.');
        }

        return redirect()->route('access.pending');
    }
}
