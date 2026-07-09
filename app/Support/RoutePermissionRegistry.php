<?php

namespace App\Support;

use Illuminate\Routing\Route as RouteContract;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Catalogue of named workspace routes that the per-route permission middleware
 * guards. Used by the sync command, the permission UI, and the role seeder so
 * the source of truth is the route table itself.
 */
class RoutePermissionRegistry
{
    public const MIDDLEWARE_ALIAS = 'route.permission';

    /**
     * Module → human label map. Drives the grouped view in the permissions UI
     * and the bulk role-mapping helper in the seeder. Any new module just adds
     * an entry here.
     */
    public const MODULES = [
        'dashboard' => 'Dashboard',
        'messages' => 'Internal Messages',
        'clients' => 'Clients',
        'intakes' => 'Client Intakes',
        'matters' => 'Matters',
        'litigation' => 'Litigation',
        'calendar' => 'Firm Calendar',
        'recoveries' => 'Recoveries',
        'land-titles' => 'Securities',
        'finance' => 'Finance',
        'expenses' => 'Expenses',
        'petty-cash' => 'Petty Cash',
        'ledger' => 'Ledger',
        'hr' => 'Human Resources',
        'staff' => 'Staff',
        'leave' => 'Leave Management',
        'requisitions' => 'Requisitions',
        'access' => 'Access Control',
        'branches' => 'Branches',
        'holidays' => 'Public Holidays',
        'settings' => 'Settings',
    ];

    /**
     * All route names currently protected by the route.permission middleware.
     */
    public function routeNames(): Collection
    {
        return collect(Route::getRoutes()->getRoutes())
            ->filter(function (RouteContract $route) {
                $name = $route->getName();
                if (! $name) {
                    return false;
                }

                return in_array(self::MIDDLEWARE_ALIAS, $route->gatherMiddleware(), true);
            })
            ->map(fn (RouteContract $route) => $route->getName())
            ->unique()
            ->sort()
            ->values();
    }

    /**
     * Route names grouped by their module slug (first dotted segment), with
     * the module label preserved for display purposes.
     */
    public function grouped(): Collection
    {
        return $this->routeNames()
            ->groupBy(fn (string $name) => $this->moduleSlug($name))
            ->sortKeys();
    }

    public function moduleSlug(string $routeName): string
    {
        if (! Str::contains($routeName, '.')) {
            return $routeName;
        }

        return Str::before($routeName, '.');
    }

    public function moduleLabel(string $slug): string
    {
        return self::MODULES[$slug] ?? Str::headline(str_replace('-', ' ', $slug));
    }

    public function isRouteBound(string $permissionName): bool
    {
        return $this->routeNames()->contains($permissionName);
    }

    /**
     * Used by the seeder to translate a list of module slugs into the full
     * list of route-name permissions inside those modules.
     */
    public function permissionsForModules(array $moduleSlugs): array
    {
        return $this->grouped()
            ->only($moduleSlugs)
            ->flatten()
            ->values()
            ->all();
    }
}
