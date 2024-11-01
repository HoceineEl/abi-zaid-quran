<?php

namespace Database\Seeders;

use App\Models\MemoGroup;
use App\Models\Teacher;
use Illuminate\Database\Seeder;

class MemoGroupSeeder extends Seeder
{
    public function run(): void
    {
        $teachers = Teacher::all();

        foreach ($teachers as $teacher) {
            MemoGroup::factory()
                ->count(rand(1, 3))
            ;
        }
    }
}
