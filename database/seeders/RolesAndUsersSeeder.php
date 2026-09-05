<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class RolesAndUsersSeeder extends Seeder
{
    public function run(): void
    {
        // Create roles
        $adminRole = Role::firstOrCreate([
            'name' => 'Admin',
            'guard_name' => 'web',
        ]);

        $employeeRole = Role::firstOrCreate([
            'name' => 'Employee',
            'guard_name' => 'web',
        ]);

        // Create Admin User
        $admin = User::firstOrCreate(
            [
                'email' => 'admin@example.com',
            ],
            [
                'name' => 'Admin',
                'password' => Hash::make('password'),
            ]
        );

        $admin->assignRole($adminRole);

        // Create Employee User
        $employee = User::firstOrCreate(
            [
                'email' => 'employee@example.com',
            ],
            [
                'name' => 'Employee',
                'password' => Hash::make('password'),
            ]
        );

        $employee->assignRole($employeeRole);
    }
}