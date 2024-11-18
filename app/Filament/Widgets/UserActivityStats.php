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
        $query = Progress::whereDate('date', today())
            ->selectRaw('created_by, COUNT(*) as count')
            ->groupBy('created_by');

        // Filter by managed groups if not admin
        if (!auth()->user()->isAdministrator()) {
            $query->whereIn('student_id', function ($q) {
                $q->select('id')
                    ->from('students')
                    ->whereIn('group_id', auth()->user()->managedGroups()->pluck('groups.id'));
            });
        }

        $averageRecordsPerManager = $query->get()->average('count') ?? 0;

        return [
            Stat::make('متوسط التسجيلات لكل مشرف', Number::format(round($averageRecordsPerManager)))
                ->description('متوسط عدد التسجيلات لكل مشرف اليوم')
                ->descriptionIcon('heroicon-m-document-check')
                ->chart([10, 15, 20, round($averageRecordsPerManager)])
                ->color('info'),

        ];
    }
}
