<?php

namespace App\Filament\Association\Resources\GroupResource\RelationManagers;

use App\Models\Memorizer;
use App\Models\Attendance;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;

class AttendancesRelationManager extends RelationManager
{
    protected static string $relationship = 'memorizers';

    protected static bool $isLazy = false;

    protected static ?string $title = 'الحضور';

    protected static ?string $navigationLabel = 'الحضور';

    protected static ?string $modelLabel = 'حضور';

    protected static ?string $pluralModelLabel = 'الحضور';

    public $dateFrom;

    public $dateTo;

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        $dateFrom = $this->dateFrom ?? now()->subDays(4)->format('Y-m-d');
        $dateTo = $this->dateTo ?? now()->format('Y-m-d');

        $attendancePerDay = $this->ownerRecord->memorizers->mapWithKeys(function ($memorizer) use ($dateFrom, $dateTo) {
            return [
                $memorizer->id => $memorizer->attendances
                    ->whereBetween('date', [$dateFrom, $dateTo])
                    ->groupBy('date')
            ];
        });

        $dateRange = new \DatePeriod(
            new \DateTime($dateFrom),
            new \DateInterval('P1D'),
            (new \DateTime($dateTo))->modify('+1 day')
        );

        $attendanceColumns = collect();
        foreach ($dateRange as $date) {
            $formattedDate = $date->format('Y-m-d');
            $day = $date->format('d/m');
            $attendanceColumns->push(
                IconColumn::make("attendance_day_{$formattedDate}")
                    ->label($day)
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->getStateUsing(function ($record) use ($attendancePerDay, $formattedDate) {
                        return isset($attendancePerDay[$record->id][$formattedDate]);
                    })
            );
        }

        return $table
            ->deferFilters()
            ->columns([
                TextColumn::make('name')
                    ->label('الاسم')
                    ->sortable(),
                ...$attendanceColumns->toArray(),
            ])
            ->filters([
                Filter::make('date')
                    ->form([
                        DatePicker::make('date_from')
                            ->label('من تاريخ')
                            ->default(now()->subDays(4))
                            ->reactive()
                            ->afterStateUpdated(fn($state) => $this->dateFrom = $state ?? now()->subDays(4)->format('Y-m-d')),
                        DatePicker::make('date_to')
                            ->label('إلى تاريخ')
                            ->default(now())
                            ->reactive()
                            ->afterStateUpdated(fn($state) => $this->dateTo = $state ?? now()->format('Y-m-d')),
                    ]),
            ])
            ->headerActions([
                Action::make('mark_others_as_absent')
                    ->label('تسجيل البقية كغائبين اليوم')
                    ->color('danger')
                    ->action(function () {
                        $selectedDate = now()->format('Y-m-d');
                        $this->ownerRecord->memorizers->filter(function ($memorizer) use ($selectedDate) {
                            return $memorizer->attendances->where('date', $selectedDate)->count() == 0;
                        })->each(function ($memorizer) use ($selectedDate) {
                            Attendance::create([
                                'memorizer_id' => $memorizer->id,
                                'date' => $selectedDate,
                                'status' => 'absent',
                            ]);
                            Notification::make()
                                ->title('تم تسجيل الطالب ' . $memorizer->name . ' كغائب اليوم')
                                ->success()
                                ->send();
                        });
                    }),
                Action::make('group_attendance')
                    ->label('تسجيل حضور جماعي')
                    ->color('success')
                    ->form([
                        Grid::make()
                            ->schema([
                                DatePicker::make('date')
                                    ->label('التاريخ')
                                    ->default(now())
                                    ->required(),
                                Textarea::make('notes')
                                    ->label('ملاحظات')
                                    ->rows(3),
                            ]),
                    ])
                    ->action(function (array $data) {
                        $this->ownerRecord->memorizers->each(function ($memorizer) use ($data) {
                            Attendance::firstOrCreate(
                                [
                                    'memorizer_id' => $memorizer->id,
                                    'date' => $data['date'],
                                ],
                                [
                                    'status' => 'present',
                                    'notes' => $data['notes'] ?? null,
                                ]
                            );
                        });
                        Notification::make()
                            ->title('تم تسجيل الحضور الجماعي بنجاح')
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                Action::make('toggle_attendance')
                    ->label('تغيير حالة الحضور')
                    ->icon('heroicon-o-arrow-path')
                    ->action(function (Memorizer $record, $action) {
                        $today = now()->format('Y-m-d');
                        $attendance = Attendance::firstOrCreate(
                            ['memorizer_id' => $record->id, 'date' => $today],
                            ['status' => 'absent']
                        );

                        $attendance->status = $attendance->status === 'present' ? 'absent' : 'present';
                        $attendance->save();

                        Notification::make()
                            ->title('تم تحديث حالة الحضور')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([]);
    }
}
