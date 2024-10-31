<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Create admin user
        User::create([
            'name' => 'Admin User',
            'email' => 'admin@admin.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'phone' => '1234567890',
        ]);

        // Create association user
        User::create([
            'name' => 'Association User',
            'email' => 'admin@association.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'phone' => '0987654321',
        ]);

        // Create additional users
        User::factory(10)->create();
    }
}
