<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'view dashboard',
            'view messages',
            'view listings',
            'create listings',
            'edit listings',
            'delete listings',
            'view groups',
            'create groups',
            'edit groups',
            'delete groups',
            'view contacts',
            'view categories',
            'create categories',
            'edit categories',
            'delete categories',
            'view agents',
            'view users',
            'create users',
            'edit users',
            'delete users',
            'view settings',
            'edit settings',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $admin = Role::firstOrCreate(['name' => 'admin']);
        $operator = Role::firstOrCreate(['name' => 'operator']);
        $viewer = Role::firstOrCreate(['name' => 'viewer']);

        $admin->syncPermissions(Permission::all());

        $operator->syncPermissions([
            'view dashboard',
            'view messages',
            'view listings',
            'create listings',
            'edit listings',
            'view groups',
            'view contacts',
            'view categories',
            'view agents',
        ]);

        $viewer->syncPermissions([
            'view dashboard',
            'view messages',
            'view listings',
            'view groups',
            'view contacts',
            'view categories',
        ]);
    }
}
