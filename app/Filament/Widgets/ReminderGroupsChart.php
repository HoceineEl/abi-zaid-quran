<?php

namespace App\Filament\Widgets;

use App\Helpers\PhoneHelper;
use App\Models\Group;
use App\Models\Student;
use App\Models\WhatsAppMessageHistory;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class ReminderGroupsChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'حالة التذكيرات لكل مجموعة';

    protected static ?string $maxHeight = '500px';

    protected static ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        $date = $this->filters['date'] ?? now()->toDateString();

        // One query: all reminded phones for this date
        $remindedPhones = WhatsAppMessageHistory::query()
            ->whereDate('created_at', $date)
            ->pluck('recipient_phone')
            ->toArray();

        $remindedPhonesSet = array_flip($remindedPhones);

        // One query: all students with phones and group IDs
        $students = Student::query()
            ->whereNotNull('phone')
            ->get(['id', 'phone', 'group_id']);

        // Count reminded students per group
        $remindedByGroup = $students
            ->filter(function ($s) use ($remindedPhonesSet) {
                $cleaned = PhoneHelper::cleanPhoneNumber($s->phone);

                return $cleaned && isset($remindedPhonesSet[$cleaned]);
            })
            ->groupBy('group_id')
            ->map->count();

        // One query: all groups with managers
        $groups = Group::query()
            ->with('managers')
            ->orderBy('name')
            ->get();

        // Sort: reminded groups first (descending), then not-reminded
        $groups = $groups->sortByDesc(fn ($g) => $remindedByGroup->get($g->id, 0))->values();

        $labels = [];
        $data = [];
        $colors = [];

        foreach ($groups as $group) {
            $managerName = $group->managers->pluck('name')->first() ?? 'بدون مشرف';
            $count = $remindedByGroup->get($group->id, 0);

            $labels[] = $managerName.' - '.$group->name.($count > 0 ? " ({$count})" : '');
            $data[] = $count;
            $colors[] = $count > 0 ? 'rgba(16, 185, 129, 0.8)' : 'rgba(239, 68, 68, 0.6)';
        }

        return [
            'datasets' => [
                [
                    'label' => 'عدد الطلاب المذكَّرين',
                    'data' => $data,
                    'backgroundColor' => $colors,
                    'borderWidth' => 0,
                    'borderRadius' => 4,
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
                    'ticks' => [
                        'stepSize' => 1,
                    ],
                ],
            ],
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],
        ];
    }
}
