<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RecoveryImportTemplatePermissionSeeder extends Seeder
{
    private const MANAGER_ROUTES = [
        'recoveries.templates.store',
        'recoveries.templates.download',
        'recoveries.templates.destroy',
        'recoveries.clients.portfolios.store',
    ];

    public function run(): void
    {
        $permissions = collect(self::MANAGER_ROUTES)
            ->map(fn (string $name) => Permission::findOrCreate($name, 'web'));

        Role::where('guard_name', 'web')
            ->whereIn('name', ['Super Admin', 'Administrator', 'Managing Partner', 'Recoveries Manager'])
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permissions));
    }
}
