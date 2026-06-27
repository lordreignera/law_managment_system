<?php

namespace App\Providers;

use App\Models\Branch;
use App\Models\CompanySetting;
use App\Models\Department;
use App\Models\User;
use App\Support\Sms\AfricasTalkingGateway;
use App\Support\Sms\LogSmsGateway;
use App\Support\Sms\SmsGateway;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SmsGateway::class, function () {
            return match (config('sms.default')) {
                'africastalking' => new AfricasTalkingGateway(
                    (string) config('sms.africastalking.username', 'sandbox'),
                    config('sms.africastalking.api_key'),
                    config('sms.africastalking.sender_id'),
                    (bool) config('sms.africastalking.sandbox', true),
                ),
                default => new LogSmsGateway(),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);

        // Super Admin role bypasses every authorization check (including the
        // per-route permission middleware). Done globally via Gate::before so
        // we don't need to special-case it in middleware or controllers.
        Gate::before(function (User $user, string $ability) {
            return $user->hasRole('Super Admin') ? true : null;
        });

        View::composer('*', function ($view) {
            $setting = Schema::hasTable('company_settings')
                ? CompanySetting::current()
                : new CompanySetting(CompanySetting::defaults());

            $view->with('companySetting', $setting);
        });

        View::composer('auth.register', function ($view) {
            $view->with([
                'branches' => Schema::hasTable('branches') ? Branch::where('is_active', true)->orderBy('name')->get() : collect(),
                'departments' => Schema::hasTable('departments') ? Department::where('is_active', true)->orderBy('name')->get() : collect(),
                'roles' => Schema::hasTable('roles')
                    ? Role::whereNotIn('name', ['Super Admin', 'Administrator'])->orderBy('name')->get()
                    : collect(),
            ]);
        });

        // Auto-sync the route → permission catalogue in local development. The
        // booted() callback runs after all providers (including the routing
        // provider) so Route::getRoutes() is fully populated by the time the
        // sync command inspects it.
        $this->app->booted(function () {
            $this->autoSyncRoutePermissions();
        });
    }

    /**
     * Re-run kfms:sync-route-permissions when routes/web.php is modified.
     *
     * Skipped in production (where the deploy pipeline runs the command
     * explicitly) and skipped from CLI (so artisan commands don't pay the
     * cost or risk reentrancy).
     */
    protected function autoSyncRoutePermissions(): void
    {
        if ($this->app->environment('production')) {
            return;
        }

        if ($this->app->runningInConsole()) {
            return;
        }

        if (! Schema::hasTable('permissions')) {
            return;
        }

        $routesPath = base_path('routes/web.php');
        if (! is_file($routesPath)) {
            return;
        }

        $markerPath = storage_path('framework/cache/kfms-routes-mtime');
        $currentMtime = (int) filemtime($routesPath);
        $storedMtime = is_file($markerPath) ? (int) file_get_contents($markerPath) : 0;

        if ($currentMtime === $storedMtime) {
            return;
        }

        try {
            Artisan::call('kfms:sync-route-permissions', ['--quiet-output' => true]);
            @file_put_contents($markerPath, (string) $currentMtime);
        } catch (\Throwable $e) {
            // Don't let a sync failure break dev pages; the operator can run
            // `php artisan kfms:sync-route-permissions` manually to surface
            // the underlying error.
        }
    }
}
