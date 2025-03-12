<?php

namespace Database\Seeders;

use App\Models\Group;
use App\Models\Student;
use Illuminate\Database\Seeder;

class StudentSeeder extends Seeder
{
    public function run(): void
    {
        $groups = Group::all();

        foreach ($groups as $group) {
            Student::factory()
                ->count(10)
                ->create([
                    'group_id' => $group->id,
                ]);
        }
    }
}
