<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforce per-route permissions: the current route name itself is the
 * permission key. A user with the matching permission (granted directly or
 * via any of their roles) may proceed; otherwise they get a 403.
 *
 * Super Admin role bypass is handled globally by Gate::before in
 * AppServiceProvider, so we never need to special-case it here.
 */
class EnsureRoutePermission
{
    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();
        $routeName = $route?->getName();

        // Unnamed routes (rare) are allowed through; they typically belong to
        // framework internals like /up health-checks.
        if (! $routeName) {
            return $next($request);
        }

        $user = $request->user();
        if (! $user) {
            throw new AuthenticationException();
        }

        if ($user->can($routeName)) {
            return $next($request);
        }

        abort(403, "You do not have permission to use {$routeName}.");
    }
}
