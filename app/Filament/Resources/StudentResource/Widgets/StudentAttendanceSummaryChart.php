<?php

declare(strict_types=1);

namespace App\Filament\Resources\StudentResource\Widgets;

use App\Models\Student;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Model;

class StudentAttendanceSummaryChart extends ChartWidget
{
    protected static ?string $heading = 'توزيع الحضور والغياب (30 يوم)';
    protected static ?string $maxHeight = '300px';
    protected static ?string $pollingInterval = null;
    protected int|string|array $columnSpan = 1;

    public ?Model $record = null;

    protected function getData(): array
    {
        /** @var Student $student */
        $student = $this->record;

        $since = now()->subDays(30)->startOfDay()->format('Y-m-d');

        $groupActiveDates = collect(
            $student->group?->progresses()
                ->where('date', '>=', $since)
                ->distinct('date')
                ->pluck('date')
                ->toArray() ?? []
        );

        $progresses = $student->progresses()
            ->where('date', '>=', $since)
            ->whereIn('date', $groupActiveDates->toArray())
            ->get();

        $presentCount = $progresses->where('status', 'memorized')->count();

        $unexcusedCount = $progresses
            ->where('status', 'absent')
            ->filter(fn($p) => (int) $p->with_reason === 0)
            ->count();

        $excusedCount = $progresses
            ->where('status', 'absent')
            ->filter(fn($p) => (int) $p->with_reason === 1)
            ->count();

        // Days when the group met but the student has no record at all
        $noRecordCount = max(0, $groupActiveDates->count() - $progresses->count());

        $labels = ['حاضر', 'غائب بدون عذر', 'غائب بعذر'];
        $data = [$presentCount, $unexcusedCount, $excusedCount];
        $colors = ['#10B981', '#EF4444', '#F59E0B'];

        if ($noRecordCount > 0) {
            $labels[] = 'غير مسجل';
            $data[] = $noRecordCount;
            $colors[] = '#9CA3AF';
        }

        return [
            'datasets' => [
                [
                    'data' => $data,
                    'backgroundColor' => $colors,
                    'borderColor' => '#ffffff',
                    'borderWidth' => 3,
                    'hoverOffset' => 8,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'cutout' => '65%',
            'plugins' => [
                'legend' => ['display' => true, 'position' => 'bottom'],
                'tooltip' => [
                    'callbacks' => [
                        'label' => "function(ctx){
                            var total = ctx.dataset.data.reduce(function(a,b){return a+b;},0);
                            var pct = total > 0 ? Math.round(ctx.parsed/total*100) : 0;
                            return ' ' + ctx.label + ': ' + ctx.parsed + ' يوم (' + pct + '%)';
                        }",
                    ],
                ],
            ],
        ];
    }
}
