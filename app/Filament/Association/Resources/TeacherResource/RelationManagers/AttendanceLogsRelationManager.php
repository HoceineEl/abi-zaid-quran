<?php

namespace App\Filament\Association\Resources\TeacherResource\RelationManagers;

use App\Filament\Association\Resources\GroupResource;
use App\Filament\Association\Resources\MemorizerResource;
use App\Models\Attendance;
use App\Models\MemoGroup;
use Carbon\Carbon;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;

class AttendanceLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'attendanceLogs';

    protected static ?string $title = 'سجل تسجيل الحضور';

    protected static ?string $modelLabel = 'حضور';

    protected static ?string $pluralModelLabel = 'سجلات الحضور';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn(Builder $query) => $query->whereDate('date', today()))
            ->columns([
                Tables\Columns\TextColumn::make('memorizer.name')
                    ->label('اسم الطالب(ة)')
                    ->url(fn(Attendance $record) => MemorizerResource::getUrl('edit', ['record' => $record->memorizer->id]))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('group.name')
                    ->label('المجموعة')
                    ->searchable()
                    ->url(fn(Attendance $record) => GroupResource::getUrl('view', ['record' => $record->group->id]))
                    ->sortable(),

                Tables\Columns\IconColumn::make('check_in_time')
                    ->label('الحالة')
                    ->boolean()
                    ->trueIcon('tabler-user-check')
                    ->falseIcon('tabler-user-x')
                    ->trueColor('success')
                    ->tooltip(fn(Attendance $record) => $record->check_in_time ? 'حضر' : 'غائب')
                    ->falseColor('danger'),
                TextColumn::make('score')
                    ->label('العلامة')
                    ->badge(),
                Tables\Columns\TextColumn::make('note')
                    ->label('ملاحظات')
                    ->limit(50),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاريخ التسجيل')
                    ->dateTime()
                    ->formatStateUsing(fn(string $state): string => Carbon::parse($state)->translatedFormat('d M Y h:i A'))
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('group_id')
                    ->label('المجموعة')
                    ->options(fn() => $this->ownerRecord->memoGroups()->pluck('name', 'id')->toArray())
                    ->modifyQueryUsing(function (Builder $query, array $data): Builder {
                        if ($data['value']) {
                            return $query->whereHas('memorizer.group', function (Builder $query) use ($data): Builder {
                                return $query->where('id', $data['value']);
                            });
                        }
                        return $query;
                    })
                    ->searchable(),

                Filter::make('date')
                    ->label('تاريخ الحضور')
                    ->form([
                        DatePicker::make('date')
                            ->label('التاريخ')
                            ->default(now()),
                    ])
                    ->indicateUsing(fn($state) => $state['date'] ? Carbon::parse($state['date'])->translatedFormat('d M Y') : null)
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['date'],
                            fn(Builder $query, $date): Builder => $query->whereDate('date', $date)
                        );
                    }),

                Filter::make('has_check_in')
                    ->label('حالة الحضور')
                    ->query(fn(Builder $query): Builder => $query->whereNotNull('check_in_time'))
                    ->toggle(),

                Filter::make('has_check_out')
                    ->label('حالة الانصراف')
                    ->query(fn(Builder $query): Builder => $query->whereNotNull('check_out_time'))
                    ->toggle(),
            ])
            ->actions([
                //
            ])
            ->bulkActions([
                //
            ]);
    }
}
