<?php

namespace Database\Seeders;

use App\Helpers\ProgressFormHelper;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;

class ProgressSeeder extends Seeder
{
    public function run()
    {
        $faker = Faker::create('ar_SA');
        $students = Student::all();

        foreach ($students as $student) {
            $managers = $student->group->managers->pluck('id')->toArray();

            if (empty($managers)) {
                // Fallback to a default user ID if no managers are found
                $managers = [User::first()->id];
            }

            for ($j = 0; $j < 30; $j++) { // Loop for the last 30 days
                // $pageData = ProgressFormHelper::calculateNextProgress($student);

                $createdBy = $faker->randomElement($managers);

                $progress = $student->progresses()->create([
                    'created_by' => $createdBy,
                    'date' => Carbon::now()->subDays($j)->toDateString(),
                    'status' => $faker->randomElement(['memorized', 'absent']),
                    'page_id' => $j + 1,
                    'comment' => $faker->sentence,
                    'lines_from' => null,
                    'lines_to' => null,
                    'notes' => $faker->sentence,
                ]);
            }
        }
    }
}
