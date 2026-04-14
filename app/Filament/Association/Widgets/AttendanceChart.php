<?php

namespace App\Filament\Association\Widgets;

use App\Models\Attendance;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Carbon;

class AttendanceChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'سجل الحضور والغياب';
    protected ?string $maxHeight = '300px';
    protected ?string $pollingInterval = '30s';

    protected function getData(): array
    {
        $dateFrom = $this->pageFilters['date_from'] ?? now()->startOfYear();
        $dateTo = $this->pageFilters['date_to'] ?? now();

        $data = Attendance::whereBetween('date', [$dateFrom, $dateTo])
            ->selectRaw('DATE(date) as date')
            ->selectRaw('COUNT(CASE WHEN check_in_time IS NOT NULL THEN 1 END) as present')
            ->selectRaw('COUNT(CASE WHEN check_in_time IS NULL THEN 1 END) as absent')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'الطلاب الحاضرون',
                    'data' => $data->pluck('present'),
                    'backgroundColor' => '#10B981',
                ],
                [
                    'label' => 'الطلاب الغائبون',
                    'data' => $data->pluck('absent'),
                    'backgroundColor' => '#EF4444',
                ],
            ],
            'labels' => $data->map(fn($item) => Carbon::parse($item->date)->format('d/m')),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
