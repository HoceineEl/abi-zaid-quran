<?php

namespace App\Filament\Association\Widgets;

use App\Models\Attendance;
use App\Models\Memorizer;
use App\Models\Payment;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ComprehensivePerformanceWidget extends BaseWidget
{
    use InteractsWithPageFilters;

    protected static ?string $pollingInterval = '300s';

    protected function getStats(): array
    {
        $dateFrom = $this->filters['date_from'] ?? now()->startOfYear();
        $dateTo = $this->filters['date_to'] ?? now();

        // Calculate consistency rate
        $consistentStudents = Memorizer::whereHas('attendances', function ($query) use ($dateFrom, $dateTo) {
            $query->select('memorizer_id')
                ->whereBetween('date', [$dateFrom, $dateTo])
                ->whereNotNull('check_in_time')
                ->groupBy('memorizer_id')
                ->havingRaw('COUNT(DISTINCT date) >= ?', [Carbon::parse($dateTo)->diffInDays($dateFrom) * 0.8]);
        })->count();

        $totalStudents = Memorizer::count();
        $consistencyRate = $totalStudents > 0
            ? round(($consistentStudents / $totalStudents) * 100, 1)
            : 0;

        // Calculate payment compliance
        $paidStudents = Memorizer::whereHas('payments', function ($query) use ($dateFrom, $dateTo) {
            $query->whereBetween('payment_date', [$dateFrom, $dateTo]);
        })->where('exempt', false)->count();

        $nonExemptStudents = Memorizer::where('exempt', false)->count();
        $paymentCompliance = $nonExemptStudents > 0
            ? round(($paidStudents / $nonExemptStudents) * 100, 1)
            : 0;

        // Calculate overall performance score
        $overallScore = round(($consistencyRate + $paymentCompliance) / 2, 1);

        return [
            Stat::make('معدل الانتظام', $consistencyRate . '%')
                ->description('نسبة الطلاب المنتظمين في الحضور')
                ->descriptionIcon('heroicon-m-clock')
                ->chart([60, 70, 80, $consistencyRate])
                ->color('success'),

            Stat::make('نسبة الالتزام بالدفع', $paymentCompliance . '%')
                ->description('نسبة الطلاب الملتزمين بالدفع')
                ->descriptionIcon('heroicon-m-banknotes')
                ->chart([75, 80, 85, $paymentCompliance])
                ->color('warning'),

            Stat::make('الأداء العام', $overallScore . '%')
                ->description('تقييم شامل للأداء العام للمدرسة')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->chart([70, 75, 80, $overallScore])
                ->color('info'),
        ];
    }
}
