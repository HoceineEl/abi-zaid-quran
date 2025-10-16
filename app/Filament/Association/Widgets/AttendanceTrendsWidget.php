<?php

namespace App\Filament\Association\Widgets;

use App\Models\Attendance;
use App\Models\Memorizer;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class AttendanceTrendsWidget extends ChartWidget
{
    protected ?string $heading = 'تحليل نسب الحضور الأسبوعية';
    protected ?string $maxHeight = '300px';
    protected ?string $pollingInterval = '300s';

    protected function getData(): array
    {
        $data = collect(range(6, 0))->map(function ($daysAgo) {
            $date = now()->subDays($daysAgo);

            $totalStudents = Memorizer::count();
            $presentCount = Attendance::whereDate('date', $date)
                ->whereNotNull('check_in_time')
                ->count();

            $attendanceRate = $totalStudents > 0
                ? round(($presentCount / $totalStudents) * 100, 1)
                : 0;

            return [
                'date' => $date->format('Y-m-d'),
                'معدل الحضور' => $attendanceRate,
            ];
        });

        return [
            'datasets' => [
                [
                    'label' => 'معدل الحضور اليومي',
                    'data' => $data->pluck('معدل الحضور'),
                    'fill' => true,
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'borderColor' => '#10B981',
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
