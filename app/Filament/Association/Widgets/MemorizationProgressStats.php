<?php

namespace App\Filament\Association\Widgets;

use App\Enums\MemorizationScore;
use App\Models\Attendance;
use App\Models\Memorizer;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class MemorizationProgressStats extends BaseWidget
{
    use InteractsWithPageFilters;

    protected static ?string $pollingInterval = '300s';

    protected function getStats(): array
    {
        $dateFrom = $this->filters['date_from'] ?? now()->startOfYear();
        $dateTo = $this->filters['date_to'] ?? now();

        $excellentCount = Attendance::whereBetween('date', [$dateFrom, $dateTo])
            ->where('score', MemorizationScore::EXCELLENT->value)
            ->count();

        $needsImprovementCount = Attendance::whereBetween('date', [$dateFrom, $dateTo])
            ->whereIn('score', [
                MemorizationScore::POOR->value,
                MemorizationScore::NOT_MEMORIZED->value,
            ])
            ->count();

        $averageScore = Attendance::whereBetween('date', [$dateFrom, $dateTo])
            ->whereNotNull('score')
            ->avg(DB::raw('CASE 
                WHEN score = "' . MemorizationScore::EXCELLENT->value . '" THEN 5
                WHEN score = "' . MemorizationScore::VERY_GOOD->value . '" THEN 4
                WHEN score = "' . MemorizationScore::GOOD->value . '" THEN 3
                WHEN score = "' . MemorizationScore::FAIR->value . '" THEN 2
                WHEN score = "' . MemorizationScore::POOR->value . '" THEN 1
                ELSE 0 
            END'));

        return [
            Stat::make('الطلاب المتميزون', $excellentCount)
                ->description('عدد الطلاب الذين حصلوا على تقدير ممتاز')
                ->descriptionIcon('heroicon-m-star')
                ->chart([2, 4, 6, 8, 10, $excellentCount])
                ->color('success'),

            Stat::make('متوسط التقييم', number_format($averageScore ?? 0, 1) . ' / 5')
                ->description('متوسط تقييم جميع الطلاب للفترة')
                ->descriptionIcon('heroicon-m-academic-cap')
                ->chart([3, 3.5, 4, $averageScore])
                ->color('info'),

            Stat::make('يحتاجون للمتابعة', $needsImprovementCount)
                ->description('عدد الطلاب الذين يحتاجون لدعم إضافي')
                ->descriptionIcon('heroicon-m-exclamation-circle')
                ->chart([5, 4, 3, $needsImprovementCount])
                ->color('danger'),
        ];
    }
}
