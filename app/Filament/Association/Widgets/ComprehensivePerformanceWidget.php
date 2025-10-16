<?php

namespace App\Filament\Association\Widgets;

use App\Models\Memorizer;
use App\Models\Payment;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class ComprehensivePerformanceWidget extends BaseWidget
{
    use InteractsWithPageFilters;

    protected ?string $pollingInterval = '300s';

    protected function getStats(): array
    {
        $yearStart = now()->startOfYear();
        $yearEnd = now()->endOfYear();

        // Calculate consistency rate
        $consistentStudents = Memorizer::whereHas('attendances', function ($query) use ($yearStart, $yearEnd) {
            $query->select('memorizer_id')
                ->whereBetween('date', [$yearStart, $yearEnd])
                ->whereNotNull('check_in_time')
                ->groupBy('memorizer_id')
                ->havingRaw('COUNT(DISTINCT date) >= ?', [Carbon::parse($yearEnd)->diffInDays($yearStart) * 0.8]);
        })->count();

        $totalStudents = Memorizer::count();
        $consistencyRate = $totalStudents > 0
            ? round(($consistentStudents / $totalStudents) * 100, 1)
            : 0;

        // Calculate payment compliance
        $paidStudents = Memorizer::whereHas('payments', function ($query) use ($yearStart, $yearEnd) {
            $query->whereBetween('payment_date', [$yearStart, $yearEnd]);
        })->where('exempt', false)->count();

        $nonExemptStudents = Memorizer::where('exempt', false)->count();
        $paymentCompliance = $nonExemptStudents > 0
            ? round(($paidStudents / $nonExemptStudents) * 100, 1)
            : 0;

        // Calculate overall performance score
        $overallScore = round(($consistencyRate + $paymentCompliance) / 2, 1);

        return [
            Stat::make('معدل الانضباط في الحضور للسنة الحالية', $consistencyRate.'%')
                ->description('نسبة الطلاب المنتظمين في الحضور خلال السنة الحالية')
                ->descriptionIcon('heroicon-m-clock')
                ->chart([60, 70, 80, $consistencyRate])
                ->color('success'),

            Stat::make('معدل الالتزام بالرسوم للسنة الحالية', $paymentCompliance.'%')
                ->description('نسبة الطلاب المسددين للرسوم في موعدها خلال السنة الحالية')
                ->descriptionIcon('heroicon-m-banknotes')
                ->chart([75, 80, 85, $paymentCompliance])
                ->color('warning'),

            Stat::make('التقييم الشامل للسنة الحالية', $overallScore.'%')
                ->description('التقييم العام لأداء المؤسسة التعليمية للسنة الحالية')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->chart([70, 75, 80, $overallScore])
                ->color('info'),
        ];
    }
}
