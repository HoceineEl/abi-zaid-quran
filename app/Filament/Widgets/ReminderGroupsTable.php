<?php

namespace App\Filament\Widgets;

use App\Models\Group;
use App\Models\Student;
use App\Models\User;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class ReminderGroupsTable extends BaseWidget
{
    use InteractsWithPageFilters;

    protected static bool $isDiscovered = false;

    protected static ?string $heading = 'حالة التذكيرات لكل مجموعة';

    protected static ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $date = $this->filters['date'] ?? now()->toDateString();

        $remindedByGroup = Student::query()
            ->whereHas('progresses', function ($q) use ($date) {
                $q->whereDate('date', $date)
                    ->where('comment', 'message_sent_whatsapp');
            })
            ->select('id', 'group_id')
            ->get()
            ->groupBy('group_id')
            ->map->count();

        $remindedGroupIds = $remindedByGroup->keys()->toArray();

        $hiddenManagers = ['زكرياء لعليجي', 'يوسف'];

        return $table
            ->query(
                Group::query()->with('managers')
                    ->orderByRaw('CASE WHEN id IN (' . implode(',', $remindedGroupIds ?: [0]) . ') THEN 1 ELSE 0 END')
                    ->orderBy('created_at')
            )
            ->columns([
                IconColumn::make('reminder_status')
                    ->label('')
                    ->state(fn (Group $record) => $remindedByGroup->get($record->id, 0) > 0)
                    ->boolean()
                    ->size(IconColumn\IconColumnSize::Small),

                TextColumn::make('name')
                    ->label('المجموعة')
                    ->searchable()
                    ->weight('bold')
                    ->size(TextColumn\TextColumnSize::ExtraSmall)
                    ->description(fn (Group $record) => $record->managers
                        ->reject(fn ($m) => $m->isAdministrator() || in_array(trim($m->name), $hiddenManagers))
                        ->pluck('name')
                        ->join('، ') ?: 'بدون مشرف'),

                TextColumn::make('reminded_students')
                    ->label('مُذكَّرين')
                    ->state(fn (Group $record) => $remindedByGroup->get($record->id, 0))
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'success' : 'danger')
                    ->suffix(' طالب'),
            ])
            ->filters([
                TernaryFilter::make('reminder_status')
                    ->label('حالة التذكير')
                    ->placeholder('الكل')
                    ->trueLabel('تم التذكير')
                    ->falseLabel('لم يتم التذكير')
                    ->queries(
                        true: fn (Builder $query) => $query->whereIn('id', $remindedGroupIds ?: [0]),
                        false: fn (Builder $query) => $query->whereNotIn('id', $remindedGroupIds ?: [0]),
                    ),

                SelectFilter::make('manager')
                    ->label('المشرف')
                    ->searchable()
                    ->options(fn () => User::query()
                        ->where('role', '!=', 'teacher')
                        ->where('role', '!=', 'admin')
                        ->whereNotIn('name', $hiddenManagers)
                        ->whereHas('managedGroups')
                        ->pluck('name', 'id')
                        ->toArray())
                    ->query(fn (Builder $query, array $data) => $query->when(
                        $data['value'],
                        fn (Builder $q, $managerId) => $q->whereHas('managers', fn ($q) => $q->where('users.id', $managerId))
                    )),
            ])
            ->filtersLayout(FiltersLayout::AboveContent)
            ->paginated(false);
    }
}
