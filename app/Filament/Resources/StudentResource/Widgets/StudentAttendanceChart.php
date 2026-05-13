<?php

declare(strict_types=1);

namespace App\Filament\Resources\StudentResource\Widgets;

use App\Models\Student;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Model;

class StudentAttendanceChart extends ChartWidget
{
    protected static ?string $heading = 'سجل الحضور اليومي';
    protected static ?string $maxHeight = '300px';
    protected static ?string $pollingInterval = null;
    protected int|string|array $columnSpan = 'full';

    public ?Model $record = null;

    protected function getFilters(): ?array
    {
        return [
            '30' => 'آخر 30 يوم',
            '60' => 'آخر 60 يوم',
            '90' => 'آخر 90 يوم',
        ];
    }

    protected function getData(): array
    {
        /** @var Student $student */
        $student = $this->record;

        $days = (int) ($this->filter ?? 30);
        $startDate = now()->subDays($days - 1)->startOfDay()->format('Y-m-d');
        $endDate = now()->format('Y-m-d');

        // Only days the group actually held sessions — never every calendar day
        $groupActiveDates = collect(
            $student->group?->progresses()
                ->whereBetween('date', [$startDate, $endDate])
                ->distinct('date')
                ->orderBy('date', 'asc')
                ->pluck('date')
                ->toArray() ?? []
        );

        $studentProgresses = $student->progresses()
            ->whereBetween('date', [$startDate, $endDate])
            ->whereIn('date', $groupActiveDates->toArray())
            ->get()
            ->keyBy('date');

        $labels = $presentData = $unexcusedData = $excusedData = [];

        foreach ($groupActiveDates as $date) {
            $labels[] = Carbon::parse($date)->format('d/m');

            $progress = $studentProgresses->get($date);
            $status = $progress?->status;
            $withReason = $progress ? (int) $progress->with_reason : 0;

            $presentData[] = $status === 'memorized' ? 1 : 0;
            $unexcusedData[] = ($status === 'absent' && $withReason === 0) ? 1 : 0;
            $excusedData[] = ($status === 'absent' && $withReason === 1) ? 1 : 0;
        }

        return [
            'datasets' => [
                [
                    'label' => 'حاضر',
                    'data' => $presentData,
                    'borderColor' => '#10B981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.12)',
                    'pointBackgroundColor' => '#10B981',
                    'pointRadius' => 5,
                    'fill' => true,
                    'tension' => 0.3,
                ],
                [
                    'label' => 'غائب بدون عذر',
                    'data' => $unexcusedData,
                    'borderColor' => '#EF4444',
                    'backgroundColor' => 'rgba(239, 68, 68, 0.12)',
                    'pointBackgroundColor' => '#EF4444',
                    'pointRadius' => 5,
                    'fill' => true,
                    'tension' => 0.3,
                ],
                [
                    'label' => 'غائب بعذر',
                    'data' => $excusedData,
                    'borderColor' => '#F59E0B',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.12)',
                    'pointBackgroundColor' => '#F59E0B',
                    'pointRadius' => 5,
                    'fill' => true,
                    'tension' => 0.3,
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
                    'ticks' => ['stepSize' => 1],
                    'grid' => ['color' => 'rgba(0,0,0,0.04)'],
                ],
                'x' => [
                    'ticks' => ['maxRotation' => 45, 'minRotation' => 0],
                    'grid' => ['display' => false],
                ],
            ],
            'plugins' => [
                'legend' => ['display' => true, 'position' => 'top'],
                'tooltip' => ['mode' => 'index', 'intersect' => false],
            ],
            'interaction' => ['mode' => 'index', 'intersect' => false],
        ];
    }
}
