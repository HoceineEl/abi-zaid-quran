<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\Memorizer;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class AttendanceSeeder extends Seeder
{
    public function run(): void
    {
        $memorizers = Memorizer::all();
        $dates = collect(range(0, 30))->map(fn($daysAgo) => Carbon::now()->subDays($daysAgo));

        foreach ($memorizers as $memorizer) {
            foreach ($dates as $date) {
                // 80% chance of attendance
                $hasAttended = rand(1, 100) <= 80;

                Attendance::create([
                    'memorizer_id' => $memorizer->id,
                    'date' => $date,
                    'check_in_time' => $hasAttended ? $date->copy()->addHours(rand(8, 10)) : null,
                    'check_out_time' => $hasAttended ? $date->copy()->addHours(rand(11, 14)) : null,
                ]);
            }
        }
    }
}
