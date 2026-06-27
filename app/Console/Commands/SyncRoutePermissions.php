<?php

namespace App\Console\Commands;

use App\Support\RoutePermissionRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class SyncRoutePermissions extends Command
{
    protected $signature = 'kfms:sync-route-permissions
                            {--prune : Remove route-named permissions that no longer correspond to any route}
                            {--quiet-output : Suppress per-permission output}';

    protected $description = 'Create a Spatie permission for every named route protected by route.permission middleware.';

    public function handle(RoutePermissionRegistry $registry): int
    {
        // Tolerate a fresh install where migrations haven't run yet so this
        // command can sit safely inside composer post-install hooks.
        if (! Schema::hasTable('permissions')) {
            $this->warn('permissions table not found; skipping sync (run migrations first).');

            return self::SUCCESS;
        }

        $routeNames = $registry->routeNames();

        if ($routeNames->isEmpty()) {
            $this->warn('No routes carry the route.permission middleware. Nothing to sync.');

            return self::SUCCESS;
        }

        $created = 0;
        $existing = Permission::pluck('name')->all();

        foreach ($routeNames as $name) {
            if (in_array($name, $existing, true)) {
                continue;
            }
            Permission::create(['name' => $name, 'guard_name' => 'web']);
            $created++;
            if (! $this->option('quiet-output')) {
                $this->line("  + {$name}");
            }
        }

        $pruned = 0;
        if ($this->option('prune')) {
            // Only prune permissions whose name looks like a route name (contains a dot or matches a known module).
            $stale = Permission::whereNotIn('name', $routeNames)
                ->where(function ($query) use ($registry) {
                    foreach (array_keys(RoutePermissionRegistry::MODULES) as $slug) {
                        $query->orWhere('name', 'like', $slug.'.%');
                    }
                    $query->orWhere('name', 'dashboard');
                })
                ->get();

            foreach ($stale as $permission) {
                $permission->delete();
                $pruned++;
                if (! $this->option('quiet-output')) {
                    $this->line("  - {$permission->name}");
                }
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->info("Route permissions synced: {$created} created, {$pruned} pruned, ".count($routeNames).' total tracked.');

        return self::SUCCESS;
    }
}
