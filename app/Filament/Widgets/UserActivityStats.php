<?php

namespace App\Filament\Widgets;

use App\Models\Progress;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

class UserActivityStats extends BaseWidget
{
    protected static ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        // Get active managers (those who recorded progress today)

        // Calculate average records per manager
        $averageRecordsPerManager = Progress::whereDate('date', today())
            ->selectRaw('created_by, COUNT(*) as count')
            ->groupBy('created_by')
            ->get()
            ->average('count') ?? 0;

        return [
            Stat::make('متوسط التسجيلات لكل مشرف', Number::format(round($averageRecordsPerManager)))
                ->description('متوسط عدد التسجيلات لكل مشرف اليوم')
                ->descriptionIcon('heroicon-m-document-check')
                ->chart([10, 15, 20, round($averageRecordsPerManager)])
                ->color('info'),

        ];
    }
}
