<?php

namespace App\Services;

use App\Models\Group;
use Illuminate\Support\Collection;

class AttendanceReportService
{
    /**
     * Prepare export data for a single group's attendance report.
     *
     * @return array{html: string, groupName: string, presencePercentage: int, dateRange: string, showAttendanceRemark: bool}
     */
    public static function prepareGroupExportData(Group $group, ?string $date = null): array
    {
        $students = \App\Filament\Resources\GroupResource\RelationManagers\StudentsRelationManager::getSortedStudentsForAttendanceReport($group, $date);

        $countableStudents = $students->filter(fn($student) => self::shouldCountForAttendance($student));
        $presencePercentage = self::calculatePresencePercentage($countableStudents);

        $html = view('components.students-export-table', [
            'showAttendanceRemark' => true,
            'students' => $students,
            'group' => $group,
            'presencePercentage' => $presencePercentage,
        ])->render();

        return [
            'html' => $html,
            'groupName' => $group->name,
            'presencePercentage' => $presencePercentage,
            'dateRange' => 'آخر 30 يوم',
            'showAttendanceRemark' => true,
        ];
    }

    private static function shouldCountForAttendance($student): bool
    {
        if ($student->attendance_count > 0) {
            return true;
        }

        $todayProgress = $student->today_progress;
        return !($todayProgress && $todayProgress->status === 'absent' && $todayProgress->with_reason === true);
    }

    private static function calculatePresencePercentage(Collection $countableStudents): int
    {
        $totalStudents = $countableStudents->count();

        if ($totalStudents === 0) {
            return 0;
        }

        $presentStudents = $countableStudents->where('attendance_count', '>', 0)->count();
        return (int) round(($presentStudents / $totalStudents) * 100);
    }

    /**
     * Prepare export data for multiple groups.
     *
     * @return array<int, array{html: string, groupName: string, presencePercentage: int, dateRange: string, showAttendanceRemark: bool}>
     */
    public static function prepareBulkExportData(Collection $groups, ?string $date = null): array
    {
        return $groups->map(fn (Group $group) => self::prepareGroupExportData($group, $date))->values()->all();
    }
}
