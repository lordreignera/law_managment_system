<?php

use App\Http\Controllers\EmailVerificationController;
use App\Http\Middleware\EnsureClientPortalAccount;
use App\Http\Middleware\EnsureRoutePermission;
use App\Http\Middleware\EnsureStaffIsActive;
use App\Http\Middleware\PreventBrowserCaching;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        channels: __DIR__.'/../routes/channels.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->append(SecurityHeaders::class);
        $middleware->web(prepend: [PreventBrowserCaching::class]);
        $middleware->alias([
            'active.staff' => EnsureStaffIsActive::class,
            'client.portal' => EnsureClientPortalAccount::class,
            'route.permission' => EnsureRoutePermission::class,
            'permission' => PermissionMiddleware::class,
            'role' => RoleMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule) {
        $schedule->command('kfms:leave-reminders')->yearlyOn(1, 2, '08:00');
        $schedule->command('kfms:calendar-reminders')->dailyAt('07:00')->withoutOverlapping();
        $schedule->command('kfms:court-reminders')->dailyAt('07:05')->withoutOverlapping();
        $schedule->command('kfms:security-reminders')->dailyAt('07:10')->withoutOverlapping();
        $schedule->command('kfms:recovery-reminders')->dailyAt('08:00')->withoutOverlapping();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) {
            if ($exception->getStatusCode() === 419 && $request->expectsJson()) {
                return response()->json([
                    'message' => 'Your session has expired. Refresh the page and try again.',
                    'session_expired' => true,
                    'login_url' => ($request->is('client*') || $request->boolean('client_portal_logout'))
                        ? route('client.login')
                        : route('login'),
                ], 419);
            }

            if ($exception->getStatusCode() === 403 && $request->routeIs('verification.verify') && $request->user() && ! $request->expectsJson()) {
                return app(EmailVerificationController::class)->linkError($request);
            }
        });
    })->create();
