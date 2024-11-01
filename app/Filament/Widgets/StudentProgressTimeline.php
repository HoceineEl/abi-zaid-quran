<?php

namespace App\Filament\Widgets;

use App\Models\Progress;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class StudentProgressTimeline extends ChartWidget
{
    protected static ?string $heading = 'تطور الحضور والغياب';
    protected static ?string $maxHeight = '300px';
    protected static ?int $sort = 2;
    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        $data = collect(range(29, 0))->map(function ($daysAgo) {
            $date = now()->subDays($daysAgo);

            return [
                'date' => $date->format('Y-m-d'),
                'memorized' => Progress::whereDate('date', $date)
                    ->where('status', 'memorized')
                    ->count(),
                'absent' => Progress::whereDate('date', $date)
                    ->where('status', 'absent')
                    ->count(),
            ];
        });

        return [
            'datasets' => [
                [
                    'label' => 'حفظ',
                    'data' => $data->pluck('memorized'),
                    'borderColor' => '#10B981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'fill' => true,
                ],
                [
                    'label' => 'غياب',
                    'data' => $data->pluck('absent'),
                    'borderColor' => '#F59E0B',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.1)',
                    'fill' => true,
                ],
            ],
            'labels' => $data->map(fn($item) => Carbon::parse($item['date'])->format('d/m')),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
