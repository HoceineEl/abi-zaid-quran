<?php

namespace App\Filament\Widgets;

use App\Models\Group;
use App\Models\Progress;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class GroupProgressChart extends ChartWidget
{
    protected static ?string $heading = 'تقدم المجموعات';
    protected static ?string $maxHeight = '300px';
    protected static ?string $pollingInterval = '30s';

    public ?string $filter = 'week';

    protected function getFilters(): ?array
    {
        return [
            'week' => 'الأسبوع الحالي',
            'month' => 'الشهر الحالي',
            'year' => 'السنة الحالية',
        ];
    }

    protected function getData(): array
    {
        $query = Group::query()
            ->select('groups.name')
            ->selectRaw('COUNT(DISTINCT CASE WHEN progress.status = "memorized" THEN progress.student_id END) as memorized_count')
            ->selectRaw('COUNT(DISTINCT CASE WHEN progress.status = "absent" THEN progress.student_id END) as absent_count')
            ->leftJoin('students', 'groups.id', '=', 'students.group_id')
            ->leftJoin('progress', 'students.id', '=', 'progress.student_id');

        // Apply date filter
        switch ($this->filter) {
            case 'week':
                $query->where('progress.date', '>=', now()->startOfWeek());
                break;
            case 'month':
                $query->where('progress.date', '>=', now()->startOfMonth());
                break;
            case 'year':
                $query->where('progress.date', '>=', now()->startOfYear());
                break;
        }

        $data = $query->groupBy('groups.id', 'groups.name')->get();

        return [
            'datasets' => [
                [
                    'label' => 'حفظ',
                    'data' => $data->pluck('memorized_count'),
                    'backgroundColor' => '#10B981',
                ],
                [
                    'label' => 'غياب',
                    'data' => $data->pluck('absent_count'),
                    'backgroundColor' => '#EF4444',
                ],
            ],
            'labels' => $data->pluck('name'),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
