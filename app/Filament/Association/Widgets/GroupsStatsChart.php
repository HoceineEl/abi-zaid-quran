<?php

namespace App\Filament\Association\Widgets;

use App\Models\MemoGroup;
use App\Models\Memorizer;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Facades\DB;

class GroupsStatsChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'إحصائيات المجموعات';
    protected static ?string $maxHeight = '300px';
    protected static ?string $pollingInterval = '30s';

    public ?string $filter = 'all';

    protected function getFilters(): ?array
    {
        return [
            'all' => 'جميع المجموعات',
            'active' => 'المجموعات النشطة',
            'inactive' => 'المجموعات غير النشطة',
        ];
    }

    protected function getData(): array
    {
        $dateFrom = $this->filters['date_from'] ?? now()->startOfYear();
        $dateTo = $this->filters['date_to'] ?? now();

        $query = MemoGroup::query()
            ->select('memo_groups.name')
            ->selectRaw('COUNT(DISTINCT memorizers.id) as students_count')
            ->leftJoin('memorizers', function ($join) use ($dateFrom, $dateTo) {
                $join->on('memo_groups.id', '=', 'memorizers.memo_group_id')
                    ->whereBetween('memorizers.created_at', [$dateFrom, $dateTo]);
            })
            ->groupBy('memo_groups.id', 'memo_groups.name');

        if ($this->filter === 'active') {
            $query->having('students_count', '>', 0);
        } elseif ($this->filter === 'inactive') {
            $query->having('students_count', '=', 0);
        }

        $groups = $query->get();

        return [
            'datasets' => [
                [
                    'label' => 'عدد الطلاب',
                    'data' => $groups->pluck('students_count')->toArray(),
                    'backgroundColor' => '#10B981',
                ],
            ],
            'labels' => $groups->pluck('name')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
