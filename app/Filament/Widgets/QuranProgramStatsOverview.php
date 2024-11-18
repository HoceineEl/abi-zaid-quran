<?php

namespace App\Filament\Widgets;

use App\Models\Group;
use App\Models\Progress;
use App\Models\Student;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

class QuranProgramStatsOverview extends BaseWidget
{
    protected static ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $query = Student::query();
        $groupsQuery = Group::query();
        
        // Filter by managed groups if not admin
        if (!auth()->user()->isAdministrator()) {
            $managedGroupIds = auth()->user()->managedGroups()->pluck('groups.id');
            $query->whereIn('group_id', $managedGroupIds);
            $groupsQuery->whereHas('managers', function ($q) {
                $q->where('manager_id', auth()->id());
            });
        }

        // Get unique students count
        $uniqueStudents = $query->distinct('phone')->count('phone');

        // Calculate total active groups
        $activeGroups = $groupsQuery->count();

        // Calculate students needing attention (3+ absences)
        $studentsNeedingAttention = $query->whereHas('progresses', function ($q) {
            $q->where('status', 'absent');
        }, '>=', 3)->count();

        // Calculate average attendance for the last 7 days
        $weeklyAttendance = Progress::where('date', '>=', now()->subDays(7))
            ->whereNotNull('status');
        
        if (!auth()->user()->isAdministrator()) {
            $weeklyAttendance->whereIn('student_id', function ($q) {
                $q->select('id')
                    ->from('students')
                    ->whereIn('group_id', auth()->user()->managedGroups()->pluck('groups.id'));
            });
        }
        
        $weeklyAttendance = $weeklyAttendance->count();
        $dailyAverage = round($weeklyAttendance / 7);

        return [
            Stat::make('الطلاب الفريدين', Number::format($uniqueStudents))
                ->description('عدد الطلاب الفريدين حسب رقم الهاتف')
                ->descriptionIcon('heroicon-m-users')
                ->chart([7, 3, 4, 5, 6, $uniqueStudents])
                ->color('success'),

            Stat::make('متوسط الحضور اليومي', Number::format($dailyAverage))
                ->description('خلال الأسبوع الماضي')
                ->descriptionIcon('heroicon-m-calendar')
                ->chart([20, 30, 40, $dailyAverage])
                ->color('info'),

            Stat::make('المجموعات النشطة', Number::format($activeGroups))
                ->description('عدد المجموعات الحالية')
                ->descriptionIcon('heroicon-m-user-group')
                ->chart([2, 4, 3, $activeGroups])
                ->color('primary'),

            Stat::make('طلاب يحتاجون للمتابعة', Number::format($studentsNeedingAttention))
                ->description('لديهم 3 غيابات أو أكثر')
                ->descriptionIcon('heroicon-m-exclamation-circle')
                ->color('danger')
                ->chart([2, 4, 3, $studentsNeedingAttention]),
        ];
    }
}
