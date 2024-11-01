<?php

namespace App\Filament\Association\Widgets;

use App\Models\Attendance;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class AttendanceChart extends ChartWidget
{
    protected static ?string $heading = 'إحصائيات الحضور الأسبوعية';
    protected static ?string $maxHeight = '300px';
    protected static ?string $pollingInterval = '30s';

    protected function getData(): array
    {
        $data = collect(range(6, 0))->map(function ($daysAgo) {
            $date = now()->subDays($daysAgo);

            $present = Attendance::whereDate('date', $date)
                ->whereNotNull('check_in_time')
                ->count();

            $absent = Attendance::whereDate('date', $date)
                ->whereNull('check_in_time')
                ->count();

            return [
                'date' => $date->format('Y-m-d'),
                'حضور' => $present,
                'غياب' => $absent,
            ];
        });

        return [
            'datasets' => [
                [
                    'label' => 'حضور',
                    'data' => $data->pluck('حضور'),
                    'backgroundColor' => '#10B981',
                ],
                [
                    'label' => 'غياب',
                    'data' => $data->pluck('غياب'),
                    'backgroundColor' => '#EF4444',
                ],
            ],
            'labels' => $data->map(fn($item) => Carbon::parse($item['date'])->format('d/m')),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
