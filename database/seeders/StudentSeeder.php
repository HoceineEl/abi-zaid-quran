<?php

namespace Database\Seeders;

use App\Models\Group;
use App\Models\MemoGroup;
use App\Models\Memorizer;
use App\Models\Student;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;

class StudentSeeder extends Seeder
{
    public function run()
    {
        $faker = Faker::create('ar_SA');

        $groups = Group::all();

        foreach ($groups as $group) {
            for ($i = 0; $i < rand(1, 10); $i++) {
                Student::create([
                    'name' => $faker->name,
                    'group_id' => $group->id,
                    'phone' => $faker->phoneNumber,
                    'city' => $faker->city,
                ]);
            }
        }

        $groups = MemoGroup::all();

        foreach ($groups as $group) {
            for ($i = 0; $i < rand(1, 10); $i++) {
                Memorizer::create([
                    'name' => $faker->name,
                    'memo_group_id' => $group->id,
                    'phone' => $faker->phoneNumber,
                    'city' => $faker->city,
                ]);
            }
        }
    }
}
