<?php

declare(strict_types=1);

namespace App\Filament\Resources\StudentResource\Widgets;

use App\Models\Student;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Model;

class StudentWeeklyProgressChart extends ChartWidget
{
    protected static ?string $heading = 'نسبة الحضور الأسبوعية';
    protected static ?string $maxHeight = '300px';
    protected static ?string $pollingInterval = null;
    protected int|string|array $columnSpan = 1;

    public ?Model $record = null;

    protected function getData(): array
    {
        /** @var Student $student */
        $student = $this->record;

        $since = now()->subWeeks(12)->startOfWeek()->format('Y-m-d');

        $groupActiveDates = collect(
            $student->group?->progresses()
                ->where('date', '>=', $since)
                ->distinct('date')
                ->orderBy('date', 'asc')
                ->pluck('date')
                ->toArray() ?? []
        );

        $studentProgresses = $student->progresses()
            ->where('date', '>=', $since)
            ->whereIn('date', $groupActiveDates->toArray())
            ->get()
            ->keyBy('date');

        $labels = $data = $bgColors = $borderColors = [];

        foreach ($groupActiveDates->groupBy(fn($d) => Carbon::parse($d)->format('Y-W'))->sortKeys() as $dates) {
            $datesArr = $dates->values()->toArray();
            $total = count($datesArr);
            $attended = collect($datesArr)
                ->filter(fn($d) => $studentProgresses->get($d)?->status === 'memorized')
                ->count();

            $percentage = $total > 0 ? (int) round(($attended / $total) * 100) : 0;

            $weekStart = Carbon::parse($datesArr[0])->format('d/m');
            $weekEnd = Carbon::parse(end($datesArr))->format('d/m');
            $labels[] = $weekStart . ' - ' . $weekEnd;
            $data[] = $percentage;

            [$bg, $border] = match (true) {
                $percentage >= 80 => ['rgba(16, 185, 129, 0.85)', '#10B981'],
                $percentage >= 60 => ['rgba(245, 158, 11, 0.85)', '#F59E0B'],
                default           => ['rgba(239, 68, 68, 0.85)', '#EF4444'],
            };
            $bgColors[] = $bg;
            $borderColors[] = $border;
        }

        return [
            'datasets' => [
                [
                    'label' => 'نسبة الحضور',
                    'data' => $data,
                    'backgroundColor' => $bgColors,
                    'borderColor' => $borderColors,
                    'borderWidth' => 2,
                    'borderRadius' => 6,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'max' => 100,
                    'ticks' => [
                        'callback' => "function(v){ return v + '%'; }",
                    ],
                    'grid' => ['color' => 'rgba(0,0,0,0.04)'],
                ],
                'x' => [
                    'ticks' => ['maxRotation' => 45],
                    'grid' => ['display' => false],
                ],
            ],
            'plugins' => [
                'legend' => ['display' => false],
                'tooltip' => [
                    'callbacks' => [
                        'label' => "function(ctx){ return ' ' + ctx.parsed.y + '%'; }",
                    ],
                ],
            ],
        ];
    }
}
