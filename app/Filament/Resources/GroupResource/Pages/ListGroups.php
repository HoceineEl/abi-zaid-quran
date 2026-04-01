<?php

namespace App\Filament\Resources\GroupResource\Pages;

use App\Filament\Actions\CheckWhatsAppStatusAction;
use App\Filament\Resources\GroupResource;
use App\Models\Group;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class ListGroups extends ListRecords
{
    protected static string $resource = GroupResource::class;

    protected ?EloquentCollection $attendanceGroups = null;

    protected function getGroupsWithAttendance(): EloquentCollection
    {
        return $this->attendanceGroups ??= Group::with(['students' => function ($query): void {
            $query->withCount(['progresses as attendance_count' => function ($q): void {
                $q->where('date', Carbon::now()->format('Y-m-d'))
                    ->where('status', 'memorized');
            }]);
        }])->get();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->visible(auth()->user()->isAdministrator()),
            CheckWhatsAppStatusAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('جميع المجموعات')
                ->badge(Group::count()),

            'perfect' => $this->makeAttendanceTab('مجموعات مثالية (100%)', 'success', fn (int $a): bool => $a === 100),
            'high' => $this->makeAttendanceTab('مجموعات متميزة (90-99%)', 'success', fn (int $a): bool => $a >= 90 && $a < 100),
            'medium' => $this->makeAttendanceTab('مجموعات متوسطة (70-89%)', 'warning', fn (int $a): bool => $a >= 70 && $a < 90),
            'low' => $this->makeAttendanceTab('مجموعات تحتاج تحسين (<70%)', 'danger', fn (int $a): bool => $a < 70),
        ];
    }

    protected function makeAttendanceTab(string $label, string $color, \Closure $filter): Tab
    {
        return Tab::make($label)
            ->badge(fn (): int => $this->getGroupsWithAttendance()
                ->filter(fn (Group $group): bool => $filter($this->calculateGroupAttendance($group)))
                ->count()
            )
            ->badgeColor($color)
            ->modifyQueryUsing(function (Builder $query) use ($filter): Builder {
                $groupIds = $query->pluck('id')->toArray();

                if (empty($groupIds)) {
                    return $query;
                }

                $filteredIds = $this->getGroupsWithAttendance()
                    ->whereIn('id', $groupIds)
                    ->filter(fn (Group $group): bool => $filter($this->calculateGroupAttendance($group)))
                    ->pluck('id')
                    ->toArray();

                return $query->whereIn('id', $filteredIds);
            });
    }

    protected function calculateGroupAttendance(Group $group): int
    {
        if (! $group->relationLoaded('students')) {
            $group->load(['students' => function ($query): void {
                $query->withCount(['progresses as attendance_count' => function ($q): void {
                    $q->where('date', Carbon::now()->format('Y-m-d'))
                        ->where('status', 'memorized');
                }]);
            }]);
        }

        $students = $group->students;

        if ($students->isEmpty()) {
            return 0;
        }

        $presentCount = $students->where('attendance_count', '>', 0)->count();

        return (int) round(($presentCount / $students->count()) * 100);
    }

    protected function getTableRecordsPerPageSelectOptions(): array
    {
        return [10, 25, 50, 100];
    }
}
