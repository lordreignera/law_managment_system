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
        'letters' => 'Letters & Opinions',
        'calendar' => 'Firm Calendar',
        'attendance' => 'Attendance',
        'recoveries' => 'Recoveries',
        'securities' => 'Securities',
        'finance' => 'Finance',
        'expenses' => 'Expenses',
        'petty-cash' => 'Petty Cash',
        'ledger' => 'Ledger',
        'payroll' => 'Payroll',
        'reports' => 'Reporting & Analytics',
        'hr' => 'Human Resources',
        'staff' => 'Staff',
        'leave' => 'Leave Management',
        'requisitions' => 'Requisitions',
        'access' => 'Access Control',
        'branches' => 'Branches',
        'holidays' => 'Public Holidays',
        'settings' => 'Settings',
        'audit' => 'Audit & Maintenance',
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

    /**
     * Return the administrator-facing name for a permission key. The key
     * itself remains the stable authorization identifier used by routes and
     * Spatie's role assignments.
     */
    public function permissionLabel(string $permissionName): string
    {
        $knownLabels = [
            'messages.store' => 'Send internal messages',
            'messages.reply' => 'Reply to messages',
            'messages.read' => 'Mark messages as read',
            'access.users.approve' => 'Approve staff access requests',
            'access.roles.permissions.edit' => 'Open role permission manager',
            'access.roles.permissions.update' => 'Update role permissions',
            'letters.draft-preview' => 'Preview letter drafts',
            'letters.preview' => 'Preview letters',
            'letters.mark-sent' => 'Mark letters as sent',
            'leave.reschedule.edit' => 'Edit leave rescheduling',
            'leave.reschedule.update' => 'Update leave rescheduling',
            'audit.index' => 'View audit logs',
            'audit.show' => 'View audit log details',
        ];

        if (isset($knownLabels[$permissionName])) {
            return $knownLabels[$permissionName];
        }

        $parts = array_values(array_filter(explode('.', $permissionName)));
        $module = $this->moduleLabel($parts[0] ?? $permissionName);

        if (count($parts) === 1) {
            return 'Open '.$module;
        }

        $action = array_pop($parts);
        $resource = Str::headline(str_replace(['-', '_'], ' ', end($parts) ?: $parts[0]));

        return match ($action) {
            'index' => 'View '.$resource,
            'show' => 'View '.$resource.' details',
            'create' => 'Open '.$resource.' creation',
            'store' => 'Create '.$resource,
            'edit' => 'Open '.$resource.' editor',
            'update' => 'Update '.$resource,
            'destroy' => 'Delete '.$resource,
            'approve' => 'Approve '.$resource,
            'reject' => 'Reject '.$resource,
            'export' => 'Export '.$resource,
            'download' => 'Download '.$resource,
            'cancel' => 'Cancel '.$resource,
            'archive' => 'Archive '.$resource,
            'read' => 'Mark '.$resource.' as read',
            default => Str::headline(str_replace(['-', '_'], ' ', $action)).' '.$resource,
        };
    }

    /**
     * Explain the effect of a permission without changing its authorization
     * key. This is deliberately generated from the same stable key so new
     * route permissions receive a useful explanation automatically.
     */
    public function permissionDescription(string $permissionName): string
    {
        $module = $this->moduleLabel($this->moduleSlug($permissionName));
        $label = lcfirst($this->permissionLabel($permissionName));

        return 'Allows this role to '.$label.' in the '.$module.' module.';
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
