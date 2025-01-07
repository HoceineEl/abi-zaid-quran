<?php

namespace Database\Seeders;

use App\Models\MemoGroup;
use App\Models\User;
use Illuminate\Database\Seeder;

class MemoGroupSeeder extends Seeder
{
    public function run(): void
    {
        $teachers = User::where('role', 'teacher')->get();

        foreach ($teachers as $teacher) {
            MemoGroup::factory()
                ->count(rand(1, 3))
            ;
        }
    }
}
