<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]
            ->forgetCachedPermissions();

        Role::firstOrCreate([
            'name' => 'Admin',
            'guard_name' => 'web',
        ]);

        Role::firstOrCreate([
            'name' => 'Employee',
            'guard_name' => 'web',
        ]);
    }
}