<?php

namespace App\Filament\Association\Resources\GroupResource\RelationManagers;

use App\Enums\MemorizationScore;
use App\Enums\Troubles;
use App\Models\Attendance;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\IconSize;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Filament\Tables\Actions\Action;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\ActionSize;

class AttendancesScoreRelationManager extends RelationManager
{
    protected static string $relationship = 'memorizers';
    protected static ?string $title = 'الحضور والتقييم';
    protected static ?string $navigationLabel = 'الحضور والتقييم';
    protected static ?string $modelLabel = 'حضور وتقييم';
    protected static ?string $pluralModelLabel = 'الحضور والتقييم';
    protected static ?string $icon = 'heroicon-o-clipboard-document-check';

    public $dateFrom;
    public $dateTo;

    protected function canView(Model $record): bool
    {
        return !auth()->user()->isTeacher();
    }

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        $this->dateFrom = $this->dateFrom ?? now()->subDays(2)->format('Y-m-d');
        $this->dateTo = $this->dateTo ?? now()->format('Y-m-d');

        $dateRange = new \DatePeriod(
            new \DateTime($this->dateFrom),
            new \DateInterval('P1D'),
            (new \DateTime($this->dateTo))->modify('+1 day')
        );

        $attendanceColumns = collect();

        // Eager load only needed attendance attributes
        $this->ownerRecord->loadMissing(['memorizers' => function ($query) use ($dateRange) {
            $query->with(['attendances:id,memorizer_id,date,check_in_time,score' => function ($query) use ($dateRange) {
                $query->whereBetween('date', [
                    $dateRange->getStartDate()->format('Y-m-d'),
                    $dateRange->getEndDate()->format('Y-m-d')
                ]);
            }]);
        }]);

        foreach ($dateRange as $date) {
            $formattedDate = $date->format('Y-m-d');
            $day = $date->format('d/m');
            $attendanceColumns->push(
                TextColumn::make("attendance_day_{$formattedDate}")
                    ->label($day)
                    ->alignCenter()
                    ->state(function ($record) use ($formattedDate) {
                        $attendance = $record->attendances->first(function ($attendance) use ($formattedDate) {
                            return $attendance->date->format('Y-m-d') === $formattedDate;
                        });

                        if (!$attendance) {
                            return 'غ.م';
                        }

                        if (!$attendance->check_in_time) {
                            return 'absent';
                        }
                        return $attendance->score ?? 'present';
                    })
                    ->badge()
                    ->color(function ($state) {
                        if ($state === 'غ.م') return Color::Gray;
                        if ($state === 'absent') return Color::Red;
                        if ($state === 'present') return Color::Green;

                        $scores = collect(MemorizationScore::cases())->pluck('value');
                        if ($scores->contains($state)) {
                            return MemorizationScore::from($state)->getColor();
                        }

                        return Color::Gray;
                    })
                    ->icon(function ($state) {
                        if ($state === 'غ.م') return 'heroicon-o-question-mark-circle';
                        if ($state === 'absent') return 'heroicon-o-x-circle';
                        if ($state === 'present') return 'heroicon-o-check-circle';

                        $scores = collect(MemorizationScore::cases())->pluck('value');
                        if ($scores->contains($state)) {
                            return MemorizationScore::from($state)->getIcon();
                        }

                        return null;
                    })
                    ->iconPosition('before')
                    ->description(function ($record) use ($formattedDate) {
                        $attendance = $record->attendances->first(function ($attendance) use ($formattedDate) {
                            return $attendance->date->format('Y-m-d') === $formattedDate;
                        });

                        if ($attendance && ($attendance->notes || $attendance->custom_note)) {
                            return 'مشكلة';
                        }

                        return null;
                    })
                    ->action(
                        Action::make('view_details_' . $formattedDate)
                            ->modalHeading(fn($record) => "تفاصيل يوم {$day} للطالب {$record->name}")
                            ->hidden(function ($record) use ($formattedDate) {
                                return !$record->attendances->contains(function ($attendance) use ($formattedDate) {
                                    return $attendance->date->format('Y-m-d') === $formattedDate;
                                });
                            })
                            ->modalContent(function ($record) use ($formattedDate) {
                                $attendance = $record->attendances->first(function ($attendance) use ($formattedDate) {
                                    return $attendance->date->format('Y-m-d') === $formattedDate;
                                });

                                return view('filament.components.attendance-details', [
                                    'attendance' => $attendance,
                                    'memorizer' => $record,
                                    'date' => $formattedDate,
                                ]);
                            })
                            ->modalWidth('2xl')
                            ->slideOver()
                    )
            );
        }

        return $table
            ->deferFilters()
            ->columns([
                TextColumn::make('name')
                    ->label('الاسم')
                    ->getStateUsing(function ($record, $rowLoop) {
                        $number = $rowLoop->iteration;
                        return $number . '. ' . $record->name;
                    })
                    ->sortable(),
                ...$attendanceColumns->toArray(),
            ])
            ->filters([
                Filter::make('date')
                    ->columnSpanFull()
                    ->form([
                        DatePicker::make('date_from')
                            ->label('من تاريخ')
                            ->reactive()
                            ->afterStateUpdated(fn($state) => $this->dateFrom = $state ?? now()->subDays(4)->format('Y-m-d'))
                            ->default(now()->subDays(4)->format('Y-m-d')),
                        DatePicker::make('date_to')
                            ->reactive()
                            ->label('إلى تاريخ')
                            ->afterStateUpdated(fn($state) => $this->dateTo = $state ?? now()->format('Y-m-d'))
                            ->default(now()->format('Y-m-d')),
                    ], FiltersLayout::AboveContent),
            ])
            ->query(function () {
                $dateFrom = $this->dateFrom;
                $dateTo = $this->dateTo;
                return $this->ownerRecord->memorizers()
                    ->select('id', 'name')
                    ->withCount(['attendances as attendance_count' => function ($query) use ($dateFrom, $dateTo) {
                        $query->whereBetween('date', [$dateFrom, $dateTo]);
                    }])
                    ->orderByDesc('attendance_count');
            })
            ->paginated(false)
            ->recordUrl(null)
            ->headerActions([
                Action::make('export_table')
                    ->label('تصدير كصورة')
                    ->icon('heroicon-o-share')
                    ->size(ActionSize::Small)
                    ->color('success')
                    ->action(function () {
                        $date = now()->format('Y-m-d');

                        $memorizers = $this->ownerRecord->memorizers()
                            ->get();

                        $html = view('components.attendance-export-table', [
                            'memorizers' => $memorizers,
                            'group' => $this->ownerRecord,
                            'date' => $date,
                        ])->render();

                        $this->dispatch('export-table', [
                            'html' => $html,
                            'groupName' => $this->ownerRecord->name
                        ]);
                    })
            ])
            ->actions([])
            ->bulkActions([]);
    }

    public function isReadOnly(): bool
    {
        return !$this->ownerRecord->managers->contains(auth()->user());
    }
}
