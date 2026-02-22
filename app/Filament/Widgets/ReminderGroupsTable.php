<?php

namespace App\Filament\Widgets;

use App\Helpers\PhoneHelper;
use App\Models\Group;
use App\Models\Student;
use App\Models\User;
use App\Models\WhatsAppMessageHistory;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
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

    protected static ?string $heading = 'حالة التذكيرات لكل مجموعة';

    protected static ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $date = $this->filters['date'] ?? now()->toDateString();

        $remindedPhonesSet = array_flip(
            WhatsAppMessageHistory::query()
                ->whereDate('created_at', $date)
                ->pluck('recipient_phone')
                ->toArray()
        );

        $remindedByGroup = Student::query()
            ->whereNotNull('phone')
            ->get(['id', 'phone', 'group_id'])
            ->filter(function ($s) use ($remindedPhonesSet) {
                $cleaned = PhoneHelper::cleanPhoneNumber($s->phone);

                return $cleaned && isset($remindedPhonesSet[$cleaned]);
            })
            ->groupBy('group_id')
            ->map->count();

        $remindedGroupIds = $remindedByGroup->keys()->toArray();

        $hiddenManagers = ['زكرياء لعليجي', 'يوسف'];

        return $table
            ->query(
                Group::query()->with('managers')
                    ->orderByRaw('CASE WHEN id IN (' . implode(',', $remindedGroupIds ?: [0]) . ') THEN 1 ELSE 0 END')
                    ->orderBy('name')
            )
            ->contentGrid([
                'default' => 1,
                'sm' => 2,
                'lg' => 3,
                'xl' => 4,
            ])
            ->columns([
                Split::make([
                    IconColumn::make('reminder_status')
                        ->label('الحالة')
                        ->state(fn (Group $record) => $remindedByGroup->get($record->id, 0) > 0)
                        ->boolean()
                        ->size(IconColumn\IconColumnSize::Large)
                        ->grow(false),

                    Stack::make([
                        TextColumn::make('name')
                            ->label('المجموعة')
                            ->searchable()
                            ->weight('bold')
                            ->size(TextColumn\TextColumnSize::Large),

                        TextColumn::make('managers_names')
                            ->label('المشرفين')
                            ->state(fn (Group $record) => $record->managers
                                ->reject(fn ($m) => $m->isAdministrator() || in_array(trim($m->name), $hiddenManagers))
                                ->pluck('name')
                                ->join('، ') ?: 'بدون مشرف')
                            ->color('gray')
                            ->size(TextColumn\TextColumnSize::Small)
                            ->icon('heroicon-m-user')
                            ->wrap(),
                    ]),

                    TextColumn::make('reminded_students')
                        ->label('مُذكَّرين')
                        ->state(fn (Group $record) => $remindedByGroup->get($record->id, 0))
                        ->badge()
                        ->color(fn ($state) => $state > 0 ? 'success' : 'danger')
                        ->suffix(' طالب')
                        ->grow(false),
                ]),
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
