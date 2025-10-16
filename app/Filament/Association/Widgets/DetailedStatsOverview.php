<?php

namespace App\Filament\Association\Widgets;

use App\Models\Attendance;
use App\Models\Memorizer;
use App\Models\Payment;
use Exception;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DetailedStatsOverview extends BaseWidget
{
    protected ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        try {
            $startDate = now()->startOfYear();
            $endDate = now()->endOfYear();

            return [
                $this->getAverageAttendanceStat($startDate, $endDate),
                $this->getPaymentsStat($startDate, $endDate),
                $this->getAveragePaymentStat($startDate, $endDate),
                $this->getExemptStudentsStat(),
            ];
        } catch (Exception $e) {
            Log::error('Error generating detailed stats: '.$e->getMessage());

            return $this->getFallbackStats();
        }
    }

    protected function getAverageAttendanceStat(Carbon $startDate, Carbon $endDate): Stat
    {
        $attendanceData = DB::table('attendances')
            ->whereBetween('date', [$startDate, $endDate])
            ->whereNotNull('check_in_time')
            ->select(DB::raw('DATE(date) as attendance_date'), DB::raw('COUNT(*) as daily_count'))
            ->groupBy('attendance_date')
            ->get();

        $avgAttendance = $attendanceData->average('daily_count') ?? 0;
        $attendanceTrend = $attendanceData->pluck('daily_count')->toArray();

        return Stat::make('معدل الحضور اليومي للسنة', number_format($avgAttendance, 1))
            ->description('متوسط عدد الطلاب الحاضرين في اليوم الواحد خلال السنة')
            ->descriptionIcon('heroicon-m-user-group')
            ->chart($attendanceTrend)
            ->color('success');
    }

    protected function getPaymentsStat(Carbon $startDate, Carbon $endDate): Stat
    {
        $totalPayments = Payment::whereBetween('created_at', [$startDate, $endDate])
            ->sum('amount');

        return Stat::make('إجمالي الرسوم المحصّلة للسنة', number_format($totalPayments).' درهم')
            ->description('مجموع الرسوم المحصّلة خلال السنة الحالية')
            ->descriptionIcon('heroicon-m-banknotes')
            ->color('warning');
    }

    protected function getAveragePaymentStat(Carbon $startDate, Carbon $endDate): Stat
    {
        $avgPayment = Payment::whereBetween('created_at', [$startDate, $endDate])
            ->select(DB::raw('COALESCE(AVG(amount), 0) as avg_amount'))
            ->first()
            ->avg_amount ?? 0;

        return Stat::make('متوسط الرسوم السنوي للطالب', number_format($avgPayment, 1).' درهم')
            ->description('معدل الرسوم المدفوعة للطالب خلال السنة الحالية')
            ->descriptionIcon('heroicon-m-currency-dollar')
            ->color('info');
    }

    protected function getExemptStudentsStat(): Stat
    {
        $totalStudents = Memorizer::count();
        $exemptStudents = Memorizer::where('exempt', true)->count();
        $exemptPercentage = $totalStudents > 0 ? round(($exemptStudents / $totalStudents) * 100, 1) : 0;

        return Stat::make('نسبة الإعفاء من الرسوم', $exemptPercentage.'%')
            ->description(sprintf('عدد المعفيين: %d طالباً من إجمالي %d', $exemptStudents, $totalStudents))
            ->descriptionIcon('heroicon-m-shield-check')
            ->color('primary');
    }

    protected function getFallbackStats(): array
    {
        return [
            Stat::make('تعذر جلب البيانات', '---')
                ->description('حدث خطأ أثناء استرجاع الإحصائيات')
                ->color('danger'),
        ];
    }

    // Helper method to safely get attendance trend data
    protected function getAttendanceTrend(Carbon $startDate, Carbon $endDate): array
    {
        try {
            return DB::table('attendances')
                ->whereBetween('date', [$startDate, $endDate])
                ->whereNotNull('check_in_time')
                ->select(DB::raw('DATE(date) as attendance_date'), DB::raw('COUNT(*) as daily_count'))
                ->groupBy('attendance_date')
                ->orderBy('attendance_date')
                ->pluck('daily_count')
                ->toArray();
        } catch (Exception $e) {
            Log::error('Error getting attendance trend: '.$e->getMessage());

            return [];
        }
    }
}
