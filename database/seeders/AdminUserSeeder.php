<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@marketplacejamaah.com'],
            [
                'name' => 'Administrator',
                'password' => Hash::make('admin123'),
                'is_active' => true,
            ]
        );
        $admin->assignRole('admin');

        $operator = User::firstOrCreate(
            ['email' => 'operator@marketplacejamaah.com'],
            [
                'name' => 'Operator',
                'password' => Hash::make('operator123'),
                'is_active' => true,
            ]
        );
        $operator->assignRole('operator');
    }
}
