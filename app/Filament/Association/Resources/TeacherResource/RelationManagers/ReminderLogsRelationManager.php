<?php

namespace App\Filament\Association\Resources\TeacherResource\RelationManagers;

use App\Models\User;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ReminderLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'reminderLogs';

    protected static ?string $title = 'سجل التذكيرات المرسلة';

    protected static ?string $modelLabel = 'تذكير';

    protected static ?string $pluralModelLabel = 'التذكيرات';

    public function table(Table $table): Table
    {
        /** @var User $teacher */
        $teacher = $this->ownerRecord;

        return $table
            ->query(fn () => $teacher->reminderLogs())
            ->columns([
                TextColumn::make('memorizer.name')
                    ->label('اسم الطالب(ة)')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('type')
                    ->label('النوع')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'payment' => 'تذكير بالدفع',
                        'absence' => 'تذكير بالغياب',
                        'trouble' => 'تذكير بالشغب',
                        'no_memorization' => 'تذكير بعدم الحفظ',
                        'late' => 'تذكير بالتأخر',
                        default => $state,
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'payment' => 'tabler-credit-card',
                        'absence' => 'tabler-user-off',
                        'trouble' => 'tabler-alert-triangle',
                        'no_memorization' => 'tabler-book-off',
                        'late' => 'tabler-clock',
                        default => 'tabler-message',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'payment' => 'success',
                        'absence' => 'danger',
                        'trouble' => 'warning',
                        'no_memorization' => 'info',
                        'late' => 'warning',
                        default => 'secondary',
                    }),

                TextColumn::make('phone_number')
                    ->label('رقم الهاتف')
                    ->extraCellAttributes(['dir' => 'ltr'])
                    ->alignRight(),

                TextColumn::make('message')
                    ->label('الرسالة')
                    ->limit(50),

                IconColumn::make('is_parent')
                    ->label('ولي الأمر')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label('تاريخ الإرسال')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('type')
                    ->label('نوع التذكير')
                    ->options([
                        'payment' => 'تذكير بالدفع',
                        'absence' => 'تذكير بالغياب',
                        'trouble' => 'تذكير بالشغب',
                        'no_memorization' => 'تذكير بعدم الحفظ',
                        'late' => 'تذكير بالتأخر',
                    ]),
                SelectFilter::make('is_parent')
                    ->label('المرسل إليه')
                    ->options([
                        '1' => 'ولي الأمر',
                        '0' => 'الطالب(ة)',
                    ]),
                Filter::make('created_at')
                    ->label('تاريخ الإرسال')
                    ->schema([
                        DatePicker::make('created_at')
                            ->default(now())
                            ->label('تاريخ'),

                    ])
                    ->indicateUsing(fn ($state) => $state['created_at'] ? Carbon::parse($state['created_at'])->translatedFormat('d M Y') : null)
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_at'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', $date),
                            );
                    }),
            ])
            ->recordActions([
                //
            ])
            ->toolbarActions([
                //
            ]);
    }
}
