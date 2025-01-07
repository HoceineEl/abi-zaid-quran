<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            MemoGroupSeeder::class,
            MemorizerSeeder::class,
            AttendanceSeeder::class,
            PaymentSeeder::class,
            GroupSeeder::class,
            StudentSeeder::class,
            ProgressSeeder::class,
        ]);
    }
}
