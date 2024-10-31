<?php

namespace App\Filament\Association\Widgets;

use App\Models\Attendance;
use App\Models\Memorizer;
use App\Models\Payment;
use Filament\Forms\Components\DatePicker;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DetailedStatsOverview extends BaseWidget
{
    protected static ?string $pollingInterval = '30s';

    public ?string $fromDate = null;
    public ?string $toDate = null;

    protected function getFormSchema(): array
    {
        return [
            DatePicker::make('fromDate')
                ->label('من تاريخ')
                ->default(now()->startOfMonth()),
            DatePicker::make('toDate')
                ->label('إلى تاريخ')
                ->default(now()->endOfMonth()),
        ];
    }

    protected function getStats(): array
    {
        try {
            $startDate = $this->fromDate ? Carbon::parse($this->fromDate) : now()->startOfMonth();
            $endDate = $this->toDate ? Carbon::parse($this->toDate) : now()->endOfMonth();

            return [
                $this->getAverageAttendanceStat($startDate, $endDate),
                $this->getPaymentsStat($startDate, $endDate),
                $this->getAveragePaymentStat($startDate, $endDate),
                $this->getExemptStudentsStat(),
            ];
        } catch (\Exception $e) {
            Log::error('Error generating detailed stats: ' . $e->getMessage());
            return $this->getFallbackStats();
        }
    }

    protected function getAverageAttendanceStat(Carbon $startDate, Carbon $endDate): Stat
    {
        // Fixed query to properly calculate daily attendance
        $attendanceData = DB::table('attendances')
            ->whereBetween('date', [$startDate, $endDate])
            ->whereNotNull('check_in_time')
            ->select(DB::raw('DATE(date) as attendance_date'), DB::raw('COUNT(*) as daily_count'))
            ->groupBy('attendance_date')
            ->get();

        $avgAttendance = $attendanceData->average('daily_count') ?? 0;
        $attendanceTrend = $attendanceData->pluck('daily_count')->toArray();

        return Stat::make('متوسط الحضور اليومي', number_format($avgAttendance, 1))
            ->description('متوسط عدد الطلاب الحاضرين يومياً')
            ->descriptionIcon('heroicon-m-user-group')
            ->chart($attendanceTrend)
            ->color('success');
    }

    protected function getPaymentsStat(Carbon $startDate, Carbon $endDate): Stat
    {
        $totalPayments = Payment::whereBetween('payment_date', [$startDate, $endDate])
            ->sum('amount');

        return Stat::make('إجمالي المدفوعات', number_format($totalPayments) . ' درهم')
            ->description('خلال الفترة المحددة')
            ->descriptionIcon('heroicon-m-banknotes')
            ->color('warning');
    }

    protected function getAveragePaymentStat(Carbon $startDate, Carbon $endDate): Stat
    {
        // Calculate average payment per student with proper error handling
        $avgPayment = Payment::whereBetween('payment_date', [$startDate, $endDate])
            ->select(DB::raw('COALESCE(AVG(amount), 0) as avg_amount'))
            ->first()
            ->avg_amount ?? 0;

        return Stat::make('متوسط الدفع للطالب', number_format($avgPayment, 1) . ' درهم')
            ->description('متوسط المبلغ المدفوع لكل طالب')
            ->descriptionIcon('heroicon-m-currency-dollar')
            ->color('info');
    }

    protected function getExemptStudentsStat(): Stat
    {
        $totalStudents = Memorizer::count();
        $exemptStudents = Memorizer::where('exempt', true)->count();
        $exemptPercentage = $totalStudents > 0 ? round(($exemptStudents / $totalStudents) * 100, 1) : 0;

        return Stat::make('نسبة الطلاب المعفيين', $exemptPercentage . '%')
            ->description(sprintf('%d طالب معفي من أصل %d', $exemptStudents, $totalStudents))
            ->descriptionIcon('heroicon-m-shield-check')
            ->color('primary');
    }

    protected function getFallbackStats(): array
    {
        return [
            Stat::make('خطأ في البيانات', '---')
                ->description('حدث خطأ أثناء تحميل الإحصائيات')
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
        } catch (\Exception $e) {
            Log::error('Error getting attendance trend: ' . $e->getMessage());
            return [];
        }
    }
}
