<?php

namespace App\Filament\Resources\StudentResource\Widgets;

use App\Models\Student;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Model;

class StudentAttendanceChart extends ChartWidget
{
    protected static ?string $heading = 'سجل الحضور - آخر 30 يوم';
    protected static ?string $maxHeight = '300px';
    protected static ?string $pollingInterval = null;
    protected int | string | array $columnSpan = 'full';

    public ?Model $record = null;

    protected function getData(): array
    {
        /** @var Student $student */
        $student = $this->record;

        $startDate = now()->subDays(29)->startOfDay();
        $endDate = now()->endOfDay();

        $progresses = $student->progresses()
            ->whereBetween('date', [$startDate, $endDate])
            ->get()
            ->keyBy('date');

        $labels = [];
        $presentData = [];
        $absentData = [];

        foreach (CarbonPeriod::create($startDate, $endDate) as $date) {
            $labels[] = $date->format('d/m');

            $progress = $progresses->get($date->format('Y-m-d'));
            $status = $progress?->status;

            $presentData[] = $status === 'memorized' ? 1 : 0;
            $absentData[] = $status === 'absent' ? 1 : 0;
        }

        return [
            'datasets' => [
                [
                    'label' => 'حاضر',
                    'data' => $presentData,
                    'borderColor' => '#10B981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'pointBackgroundColor' => '#10B981',
                    'pointRadius' => 4,
                    'fill' => true,
                ],
                [
                    'label' => 'غائب',
                    'data' => $absentData,
                    'borderColor' => '#EF4444',
                    'backgroundColor' => 'rgba(239, 68, 68, 0.1)',
                    'pointBackgroundColor' => '#EF4444',
                    'pointRadius' => 4,
                    'fill' => true,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'max' => 1,
                    'ticks' => [
                        'stepSize' => 1,
                    ],
                ],
            ],
            'plugins' => [
                'legend' => [
                    'display' => true,
                ],
            ],
        ];
    }
}
