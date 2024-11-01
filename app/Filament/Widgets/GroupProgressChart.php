<?php

namespace App\Filament\Widgets;

use App\Models\Group;
use App\Models\Progress;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class GroupProgressChart extends ChartWidget
{
    protected static ?string $heading = 'إحصائيات المجموعات اليومية';
    protected static ?string $maxHeight = '400px';
    protected static ?string $pollingInterval = '30s';
    protected static ?int $sort = 1;
    protected int | string | array $columnSpan = 'full';



    protected function getFilters(): ?array
    {
        $dates = collect(range(0, 29))->mapWithKeys(function ($daysAgo) {
            $date = now()->subDays($daysAgo);
            return [
                $date->format('Y-m-d') => $date->format('d/m/Y')
            ];
        })->toArray();

        return $dates;
    }

    protected function getData(): array
    {
        $selectedDate = $this->filter ? Carbon::parse($this->filter) : now();

        $query = Group::query()
            ->select([
                'groups.name',
                DB::raw('COUNT(DISTINCT CASE WHEN progress.status = "memorized" THEN progress.student_id END) as memorized_count'),
                DB::raw('COUNT(DISTINCT CASE WHEN progress.status = "absent" THEN progress.student_id END) as absent_count'),
                DB::raw('COUNT(DISTINCT students.id) as total_students')
            ])
            ->leftJoin('students', 'groups.id', '=', 'students.group_id')
            ->leftJoin('progress', function ($join) use ($selectedDate) {
                $join->on('students.id', '=', 'progress.student_id')
                    ->whereDate('progress.date', $selectedDate);
            })
            ->groupBy('groups.id', 'groups.name')
            ->having('total_students', '>', 0)
            ->orderBy('groups.name');

        $data = $query->get();

        // Calculate percentages and prepare labels
        $data = $data->map(function ($group) {
            $memorizedPercentage = $group->total_students > 0
                ? round(($group->memorized_count / $group->total_students) * 100, 1)
                : 0;

            $absentPercentage = $group->total_students > 0
                ? round(($group->absent_count / $group->total_students) * 100, 1)
                : 0;

            return [
                'name' => $group->name,
                'total_students' => $group->total_students,
                'memorized_percentage' => $memorizedPercentage,
                'absent_percentage' => $absentPercentage,
                'memorized_count' => $group->memorized_count,
                'absent_count' => $group->absent_count,
            ];
        });

        return [
            'datasets' => [
                [
                    'label' => 'حضور',
                    'data' => $data->pluck('memorized_percentage'),
                    'backgroundColor' => '#10B981',
                    'stack' => 'stack0',
                ],
                [
                    'label' => 'غياب',
                    'data' => $data->pluck('absent_percentage'),
                    'backgroundColor' => '#EF4444',
                    'stack' => 'stack0',
                ],
            ],
            'labels' => $data->map(function ($group) {
                return sprintf(
                    '%s (%d/%d حاضر - %d/%d غائب)',
                    $group['name'],
                    $group['memorized_count'],
                    $group['total_students'],
                    $group['absent_count'],
                    $group['total_students']
                );
            })->toArray(),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'max' => 100,
                    'ticks' => [
                        'callback' => "function(value) { return value + '%'; }",
                    ],
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
