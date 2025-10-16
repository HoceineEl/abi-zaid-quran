<?php

namespace Database\Seeders;

use App\Models\Progress;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class ProgressSeeder extends Seeder
{
    public function run(): void
    {
        // First create a fallback user if needed
        $fallbackUser = User::factory()->create();

        $students = Student::with(['group.managers'])->get();
        $dates = collect(range(0, 30))->map(fn ($daysAgo) => Carbon::now()->subDays($daysAgo));

        foreach ($students as $student) {

            $manager = $student->group->managers()->first() ?? $fallbackUser;
            if (! $student->group->managers->contains($manager)) {
                $student->group->managers()->attach($manager);
            }

            foreach ($dates as $date) {
                $isPresent = rand(1, 100) <= 80;

                $progressData = [
                    'student_id' => $student->id,
                    'date' => $date,
                    'status' => $isPresent ? 'memorized' : 'absent',
                    'created_at' => $date,
                    'updated_at' => $date,
                ];

                Progress::create($progressData);
            }
        }
    }
}
