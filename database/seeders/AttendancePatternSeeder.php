<?php

namespace Database\Seeders;

use App\Models\Progress;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class AttendancePatternSeeder extends Seeder
{
    /**
     * Generate attendance patterns for students with different types of attendance streaks
     */
    public function run(): void
    {
        // Get a fallback user
        $fallbackUser = User::first() ?? User::factory()->create();

        // Get all students
        $students = Student::with(['group.managers'])->get();

        // Generate data for the last 30 days
        $startDate = Carbon::now()->subDays(30);
        $dates = collect(range(0, 30))->map(fn ($day) => $startDate->copy()->addDays($day));

        // Determine which days will have records for all students (90% of days)
        $daysWithRecords = [];
        foreach ($dates as $date) {
            if (rand(1, 100) <= 90) {
                $daysWithRecords[] = $date;
            }
        }

        // Different attendance pattern categories
        $patterns = [
            'excellent' => 0.2,   // 20% of students with 16-30 days streak
            'good' => 0.3,        // 30% of students with 10-15 days streak
            'fair' => 0.2,        // 20% of students with 7-9 days streak
            'poor' => 0.3,        // 30% of students with inconsistent attendance
        ];

        // Assign patterns to students proportionally
        $assignedPatterns = [];
        $studentCount = $students->count();

        $excellentCount = (int) ($studentCount * $patterns['excellent']);
        $goodCount = (int) ($studentCount * $patterns['good']);
        $fairCount = (int) ($studentCount * $patterns['fair']);

        $studentIds = $students->pluck('id')->toArray();
        shuffle($studentIds);

        $index = 0;
        for ($i = 0; $i < $excellentCount; $i++) {
            $assignedPatterns[$studentIds[$index++]] = 'excellent';
        }

        for ($i = 0; $i < $goodCount; $i++) {
            $assignedPatterns[$studentIds[$index++]] = 'good';
        }

        for ($i = 0; $i < $fairCount; $i++) {
            $assignedPatterns[$studentIds[$index++]] = 'fair';
        }

        // The rest are poor attendance
        while ($index < $studentCount) {
            $assignedPatterns[$studentIds[$index++]] = 'poor';
        }

        // Delete existing progress records (optional, comment out if you want to keep existing data)
        Progress::whereIn('student_id', $studentIds)->delete();

        // Generate attendance for each student
        foreach ($students as $student) {
            $manager = $student->group->managers()->first();
            $patternType = $assignedPatterns[$student->id] ?? 'poor';

            // Generate attendance based on pattern type
            switch ($patternType) {
                case 'excellent':
                    $this->generateExcellentAttendance($student, $daysWithRecords, $manager);
                    break;
                case 'good':
                    $this->generateGoodAttendance($student, $daysWithRecords, $manager);
                    break;
                case 'fair':
                    $this->generateFairAttendance($student, $daysWithRecords, $manager);
                    break;
                default:
                    $this->generatePoorAttendance($student, $daysWithRecords, $manager);
                    break;
            }
        }
    }

    /**
     * Generate excellent attendance (16-30 day streak)
     */
    private function generateExcellentAttendance($student, $dates, $manager): void
    {
        // Randomly pick streak length between 16-30
        $streakLength = min(16, count($dates));

        // Sort dates in descending order (newest first) to ensure streak is most recent
        $sortedDates = collect($dates)->sortByDesc(function ($date) {
            return $date->timestamp;
        })->values();

        foreach ($sortedDates as $index => $date) {
            // First $streakLength days have good attendance
            if ($index < $streakLength) {
                $status = 'memorized';
            } else {
                $status = (rand(1, 100) <= 50) ? 'memorized' : 'absent';
            }

            $this->createProgressRecord($student, $date, $status, $manager);
        }
    }

    /**
     * Generate good attendance (10-15 day streak)
     */
    private function generateGoodAttendance($student, $dates, $manager): void
    {
        // Randomly pick streak length between 10-15
        $streakLength = min(rand(10, 15), count($dates));

        // Sort dates in descending order (newest first) to ensure streak is most recent
        $sortedDates = collect($dates)->sortByDesc(function ($date) {
            return $date->timestamp;
        })->values();

        foreach ($sortedDates as $index => $date) {
            // First $streakLength days have good attendance
            if ($index < $streakLength) {
                $status = 'memorized';
            } else {
                $status = (rand(1, 100) <= 50) ? 'memorized' : 'absent';
            }

            $this->createProgressRecord($student, $date, $status, $manager);
        }
    }

    /**
     * Generate fair attendance (7-9 day streak)
     */
    private function generateFairAttendance($student, $dates, $manager): void
    {
        // Randomly pick streak length between 7-9
        $streakLength = min(rand(7, 9), count($dates));

        // Sort dates in descending order (newest first) to ensure streak is most recent
        $sortedDates = collect($dates)->sortByDesc(function ($date) {
            return $date->timestamp;
        })->values();

        foreach ($sortedDates as $index => $date) {
            // First $streakLength days have good attendance
            if ($index < $streakLength) {
                $status = 'memorized';
            } else {
                $status = (rand(1, 100) <= 50) ? 'memorized' : 'absent';
            }

            $this->createProgressRecord($student, $date, $status, $manager);
        }
    }

    /**
     * Generate poor attendance (inconsistent pattern)
     */
    private function generatePoorAttendance($student, $dates, $manager): void
    {
        // Sort dates in descending order (newest first)
        $sortedDates = collect($dates)->sortByDesc(function ($date) {
            return $date->timestamp;
        })->values();

        foreach ($sortedDates as $date) {
            // Randomly generate attendance with higher chance of absence
            $status = (rand(1, 100) <= 40) ? 'memorized' : 'absent';

            $this->createProgressRecord($student, $date, $status, $manager);
        }
    }

    /**
     * Create a progress record
     */
    private function createProgressRecord($student, $date, $status, $manager): void
    {
        Progress::create([
            'student_id' => $student->id,
            'date' => $date->format('Y-m-d'),
            'status' => $status,
            'created_by' => $manager->id ?? 1,
            'page_id' => null,
            'created_at' => $date,
            'updated_at' => $date,
        ]);
    }
}
