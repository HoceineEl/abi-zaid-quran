<?php

namespace App\Filament\Resources\StudentResource\Widgets;

use App\Models\Student;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Model;

class StudentStatsOverview extends BaseWidget
{
    protected static ?string $pollingInterval = null;

    public ?Model $record = null;

    protected function getStats(): array
    {
        /** @var Student $student */
        $student = $this->record;

        return [
            $this->buildAttendanceRatingStat($student),
            $this->buildConsecutiveAbsentStat($student),
            $this->buildTotalAbsencesStat($student),
            $this->buildDisconnectionStat($student),
        ];
    }

    private function buildAttendanceRatingStat(Student $student): Stat
    {
        $remark = $student->attendance_remark;
        $days = $remark['days'];

        $color = match (true) {
            $days <= 3 => 'success',
            $days <= 5 => 'info',
            $days <= 7 => 'warning',
            default => 'danger',
        };

        return Stat::make('تقييم الحضور', $remark['label'])
            ->description("{$days} يوم غياب في آخر 30 يوم")
            ->descriptionIcon('heroicon-m-chart-bar')
            ->color($color);
    }

    private function buildConsecutiveAbsentStat(Student $student): Stat
    {
        $days = $student->getCurrentConsecutiveAbsentDays();

        $description = match (true) {
            $days >= 3 => 'حالة حرجة',
            $days >= 2 => 'يحتاج متابعة',
            default => 'لا توجد غيابات متتالية',
        };

        $color = match (true) {
            $days >= 3 => 'danger',
            $days >= 2 => 'warning',
            default => 'success',
        };

        return Stat::make('أيام الغياب المتتالية', $days)
            ->description($description)
            ->descriptionIcon('heroicon-m-calendar-days')
            ->color($color);
    }

    private function buildTotalAbsencesStat(Student $student): Stat
    {
        $count = $student->progresses()
            ->where('status', 'absent')
            ->where(fn($q) => $q->where('with_reason', 0)->orWhereNull('with_reason'))
            ->where('date', '>=', now()->subDays(30))
            ->count();

        return Stat::make('إجمالي الغيابات (30 يوم)', $count)
            ->description('بدون عذر')
            ->descriptionIcon('heroicon-m-x-circle')
            ->color('info');
    }

    private function buildDisconnectionStat(Student $student): Stat
    {
        $activeDisconnection = $student->disconnections()
            ->where('has_returned', false)
            ->latest()
            ->first();

        if ($activeDisconnection) {
            return Stat::make('حالة الانقطاع', 'منقطع')
                ->description('منذ ' . $activeDisconnection->disconnection_date->format('Y-m-d'))
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger');
        }

        return Stat::make('حالة الانقطاع', 'منتظم')
            ->description('لا يوجد انقطاع حالي')
            ->descriptionIcon('heroicon-m-check-circle')
            ->color('success');
    }
}
