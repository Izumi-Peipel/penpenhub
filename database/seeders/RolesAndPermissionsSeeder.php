<?php

namespace Database\Seeders;

use App\Models\Membership;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $matrix = config('penpenhub.role_permissions');
        $all = collect($matrix)->flatten()->unique()->values();

        foreach ($all as $name) {
            Permission::findOrCreate($name, 'web');
        }

        foreach ($matrix as $roleName => $permissions) {
            Role::findOrCreate($roleName, 'web')->syncPermissions($permissions);
        }

        Role::findOrCreate('super_admin', 'web')->syncPermissions($all->all());

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Membership::flushPermissionCache();
    }
}
