<?php

declare(strict_types=1);

namespace App\Filament\Resources\StudentResource\Widgets;

use App\Models\Progress;
use App\Models\Student;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection as SupportCollection;

class StudentStatsOverview extends BaseWidget
{
    protected static ?string $pollingInterval = null;

    private const LOOKBACK_DAYS = 30;

    private const CONSECUTIVE_CHART_DAYS = 14;

    private const STATUS_MEMORIZED = 'memorized';

    private const STATUS_ABSENT = 'absent';

    public ?Model $record = null;

    protected function getStats(): array
    {
        /** @var Student $student */
        $student = $this->record;

        $since = now()->subDays(self::LOOKBACK_DAYS)->startOfDay()->format('Y-m-d');

        // All student progress records in the window (chronological for charts)
        $recentProgresses = $student->progresses()
            ->where('date', '>=', $since)
            ->orderBy('date', 'asc')
            ->get();

        // The only days that count: days the group actually had sessions
        $groupActiveDates = collect(
            $student->group?->progresses()
                ->where('date', '>=', $since)
                ->distinct('date')
                ->orderBy('date', 'asc')
                ->pluck('date')
                ->toArray() ?? []
        );

        // Restrict student records to group-active days only (correct denominator)
        $progressesOnActiveDays = $recentProgresses->whereIn('date', $groupActiveDates->toArray());

        return [
            $this->buildAttendancePercentageStat($progressesOnActiveDays, $groupActiveDates),
            $this->buildLastPresentStat($student, $progressesOnActiveDays, $groupActiveDates),
            $this->buildUnexcusedAbsencesStat($progressesOnActiveDays, $groupActiveDates),
            $this->buildExcusedAbsencesStat($progressesOnActiveDays, $groupActiveDates),
            $this->buildConsecutiveAbsentStat($student, $progressesOnActiveDays, $groupActiveDates),
            $this->buildDisconnectionStat($student),
        ];
    }

    private function buildAttendancePercentageStat(
        Collection $progressesOnActiveDays,
        SupportCollection $groupActiveDates,
    ): Stat {
        $groupWorkingDays = $groupActiveDates->count();
        $attendedDays = $progressesOnActiveDays->where('status', self::STATUS_MEMORIZED)->count();

        $percentage = $this->percentage($attendedDays, $groupWorkingDays);

        $color = match (true) {
            $percentage >= 80 => 'success',
            $percentage >= 60 => 'info',
            $percentage >= 40 => 'warning',
            default => 'danger',
        };

        $description = $groupWorkingDays > 0
            ? "{$attendedDays} حضور من {$groupWorkingDays} يوم دراسة"
            : 'لا توجد أيام دراسة مسجلة';

        // Weekly attendance % trend: oldest week → newest (left → right shows improvement/decline)
        $weeklyChart = $groupActiveDates
            ->groupBy(fn ($date) => Carbon::parse($date)->format('Y-W'))
            ->sortKeys()
            ->map(function (SupportCollection $dates) use ($progressesOnActiveDays) {
                $datesArr = $dates->values()->toArray();
                $attended = $progressesOnActiveDays
                    ->whereIn('date', $datesArr)
                    ->where('status', self::STATUS_MEMORIZED)
                    ->count();

                return $this->percentage($attended, count($datesArr));
            })
            ->values()
            ->toArray();

        return Stat::make('نسبة الحضور', "{$percentage}%")
            ->description($description)
            ->descriptionIcon('heroicon-m-chart-pie')
            ->chart($weeklyChart ?: [0])
            ->color($color);
    }

    private function buildLastPresentStat(
        Student $student,
        Collection $progressesOnActiveDays,
        SupportCollection $groupActiveDates,
    ): Stat {
        // Last memorized date across all time (not windowed — teacher needs full history)
        $lastPresent = $student->progresses()
            ->where('status', self::STATUS_MEMORIZED)
            ->latest('date')
            ->first();

        // Daily presence pattern on active days: 1=present, 0=absent/missing
        $presenceChart = $this->buildDailyChart(
            $groupActiveDates,
            $progressesOnActiveDays,
            fn (?Progress $progress) => $progress?->status === self::STATUS_MEMORIZED,
        );

        if (! $lastPresent) {
            return Stat::make('آخر يوم حضور', 'لا يوجد')
                ->description('لم يُسجَّل أي حضور بعد')
                ->descriptionIcon('heroicon-m-calendar-x-mark')
                ->chart($presenceChart ?: [0])
                ->color('danger');
        }

        $date = Carbon::parse($lastPresent->date);
        $daysDiff = (int) now()->startOfDay()->diffInDays($date->copy()->startOfDay());

        $color = match (true) {
            $daysDiff <= 3 => 'success',
            $daysDiff <= 7 => 'warning',
            default => 'danger',
        };

        $ago = match (true) {
            $daysDiff === 0 => 'اليوم',
            $daysDiff === 1 => 'أمس',
            default => "منذ {$daysDiff} أيام",
        };

        return Stat::make('آخر يوم حضور', $date->format('Y/m/d'))
            ->description($ago)
            ->descriptionIcon('heroicon-m-calendar-days')
            ->chart($presenceChart ?: [0])
            ->color($color);
    }

    private function buildUnexcusedAbsencesStat(
        Collection $progressesOnActiveDays,
        SupportCollection $groupActiveDates,
    ): Stat {
        $count = $this->countAbsences($progressesOnActiveDays, withReason: false);

        $color = match (true) {
            $count === 0 => 'success',
            $count <= 2 => 'info',
            $count <= 5 => 'warning',
            default => 'danger',
        };

        $label = match (true) {
            $count === 0 => 'لا توجد غيابات',
            $count <= 2 => 'مقبول',
            $count <= 5 => 'يحتاج متابعة',
            default => 'حالة حرجة',
        };

        // Per active day: spike of 1 = unexcused absence, shows when absences cluster
        $chart = $this->buildDailyChart(
            $groupActiveDates,
            $progressesOnActiveDays,
            fn (?Progress $progress) => $this->isAbsenceMatching($progress, withReason: false),
        );

        return Stat::make('غياب بدون عذر (30 يوم)', $this->formatDayCount($count))
            ->description($label)
            ->descriptionIcon('heroicon-m-x-circle')
            ->chart($chart ?: [0])
            ->color($color);
    }

    private function buildExcusedAbsencesStat(
        Collection $progressesOnActiveDays,
        SupportCollection $groupActiveDates,
    ): Stat {
        $count = $this->countAbsences($progressesOnActiveDays, withReason: true);

        // Per active day: spike of 1 = excused absence
        $chart = $this->buildDailyChart(
            $groupActiveDates,
            $progressesOnActiveDays,
            fn (?Progress $progress) => $this->isAbsenceMatching($progress, withReason: true),
        );

        return Stat::make('غياب بعذر (30 يوم)', $this->formatDayCount($count))
            ->description($count > 0 ? 'غياب مبرر' : 'لا توجد غيابات بعذر')
            ->descriptionIcon('heroicon-m-shield-check')
            ->chart($chart ?: [0])
            ->color('info');
    }

    private function buildConsecutiveAbsentStat(
        Student $student,
        Collection $progressesOnActiveDays,
        SupportCollection $groupActiveDates,
    ): Stat {
        $days = $student->getCurrentConsecutiveAbsentDays();

        [$description, $color] = match (true) {
            $days >= 3 => ['حالة حرجة — يتطلب تدخلاً فورياً', 'danger'],
            $days >= 2 => ['يحتاج متابعة عاجلة', 'warning'],
            $days === 1 => ['غياب واحد متتالي', 'info'],
            default => ['لا توجد غيابات متتالية', 'success'],
        };

        // Last 14 active days: 1=absent without reason, 0=present or excused
        // The trailing cluster of 1s visualises the current streak
        $chart = $this->buildDailyChart(
            $groupActiveDates->takeLast(self::CONSECUTIVE_CHART_DAYS),
            $progressesOnActiveDays,
            fn (?Progress $progress) => $this->isAbsenceMatching($progress, withReason: false),
        );

        return Stat::make('الغياب المتتالي', $this->formatDayCount($days))
            ->description($description)
            ->descriptionIcon('heroicon-m-arrow-trending-down')
            ->chart($chart ?: [0])
            ->color($color);
    }

    private function buildDisconnectionStat(Student $student): Stat
    {
        $activeDisconnection = $student->disconnections()
            ->where('has_returned', false)
            ->latest()
            ->first();

        if (! $activeDisconnection) {
            return Stat::make('حالة الانقطاع', 'منتظم')
                ->description('لا يوجد انقطاع حالي')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success');
        }

        $since = Carbon::parse($activeDisconnection->disconnection_date);
        $daysSince = (int) now()->diffInDays($since);

        return Stat::make('حالة الانقطاع', 'منقطع')
            ->description("منذ {$daysSince} يوم ({$since->format('Y/m/d')})")
            ->descriptionIcon('heroicon-m-exclamation-triangle')
            ->color('danger');
    }

    /**
     * Build a per-day chart of 1s and 0s based on whether each active day's
     * progress record matches the given predicate.
     */
    private function buildDailyChart(
        SupportCollection $dates,
        Collection $progresses,
        callable $predicate,
    ): array {
        return $dates
            ->map(fn (string $date) => $predicate($progresses->firstWhere('date', $date)) ? 1 : 0)
            ->values()
            ->toArray();
    }

    private function isAbsenceMatching(?Progress $progress, bool $withReason): bool
    {
        if (! $progress || $progress->status !== self::STATUS_ABSENT) {
            return false;
        }

        return ((int) $progress->with_reason === 1) === $withReason;
    }

    private function countAbsences(Collection $progresses, bool $withReason): int
    {
        return $progresses
            ->filter(fn (Progress $progress) => $this->isAbsenceMatching($progress, $withReason))
            ->count();
    }

    private function percentage(int $part, int $total): int
    {
        return $total > 0 ? (int) round(($part / $total) * 100) : 0;
    }

    private function formatDayCount(int $count): string
    {
        $unit = $count === 1 ? 'يوم' : 'أيام';

        return "{$count} {$unit}";
    }
}
