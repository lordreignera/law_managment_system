<?php

namespace App\Providers;

use App\Models\Branch;
use App\Models\CompanySetting;
use App\Models\Department;
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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);

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
    }
}
