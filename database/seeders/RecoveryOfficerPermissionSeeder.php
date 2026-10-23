<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RecoveryOfficerPermissionSeeder extends Seeder
{
    private const ALLOWED = [
        'recoveries.mine',
        'recoveries.mine.export',
        'recoveries.show',
        'recoveries.activities.store',
        'recoveries.skip-trace.store',
    ];

    public function run(): void
    {
        $role = Role::findOrCreate('Recovery Officer', 'web');

        foreach ($role->permissions->filter(fn (Permission $permission) => str_starts_with($permission->name, 'recoveries.')) as $permission) {
            if (! in_array($permission->name, self::ALLOWED, true)) {
                $role->revokePermissionTo($permission);
            }
        }

        $allowed = collect(self::ALLOWED)->map(fn (string $name) => Permission::findOrCreate($name, 'web'));
        $role->givePermissionTo($allowed);
    }
}
