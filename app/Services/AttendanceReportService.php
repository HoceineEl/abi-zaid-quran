<?php

namespace App\Services;

use App\Models\Group;
use Carbon\Carbon;
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

    /**
     * Prepare daily attendance summary export data for image export.
     *
     * @return array{html: string, date: string, formattedDate: string, totals: array<string, int|float>}
     */
    public static function prepareDailySummaryExportData(string $date, ?int $userId = null): array
    {
        $groups = Group::getDailyAttendanceSummary($date, $userId);

        $totalStudents = $groups->sum('total_students');
        $totalPresent = $groups->sum('present');
        $totalAbsent = $groups->sum('absent');
        $totalAbsentWithReason = $groups->sum('absent_with_reason');
        $totalNotSpecified = $groups->sum('not_specified');

        $percentage = fn (int $count): int => $totalStudents > 0
            ? (int) round($count / $totalStudents * 100)
            : 0;

        $totals = [
            'total_students' => $totalStudents,
            'present' => $totalPresent,
            'absent' => $totalAbsent,
            'absent_with_reason' => $totalAbsentWithReason,
            'not_specified' => $totalNotSpecified,
            'present_pct' => $percentage($totalPresent),
            'absent_pct' => $percentage($totalAbsent),
            'absent_reason_pct' => $percentage($totalAbsentWithReason),
            'not_specified_pct' => $percentage($totalNotSpecified),
        ];

        $html = view('components.daily-summary-export-table', compact('groups'))->render();

        return [
            'html' => $html,
            'date' => $date,
            'formattedDate' => Carbon::parse($date)->locale('ar')->translatedFormat('l، j F Y'),
            'totals' => $totals,
        ];
    }
}
