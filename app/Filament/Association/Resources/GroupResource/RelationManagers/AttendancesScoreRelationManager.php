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

class AttendancesScoreRelationManager extends RelationManager
{
    protected static string $relationship = 'memorizers';

    protected static ?string $title = 'الحضور والتقييم';
    protected static ?string $navigationLabel = 'الحضور والتقييم';
    protected static ?string $modelLabel = 'حضور وتقييم';
    protected static ?string $pluralModelLabel = 'الحضور والتقييم';

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
        foreach ($dateRange as $date) {
            $formattedDate = $date->format('Y-m-d');
            $day = $date->format('d/m');

            $attendanceColumns->push(
                TextColumn::make("attendance_day_{$formattedDate}")
                    ->label($day)
                    ->alignCenter()
                    ->state(function ($record) use ($formattedDate) {
                        $attendance = $record->attendances()
                            ->whereDate('date', $formattedDate)
                            ->first();

                        if (!$attendance) {
                            return null;
                        }

                        if (!$attendance->check_in_time) {
                            return 'absent';
                        }

                        return $attendance->score ?? 'present';
                    })
                    ->badge()
                    ->formatStateUsing(function ($state) {
                        if ($state === null) return '—';
                        if ($state === 'absent') return '✗';
                        if ($state === 'present') return '✓';
                        return $state;
                    })
                    ->color(function ($state) {
                        if ($state === null) return 'gray';
                        if ($state === 'absent') return Color::Red;
                        if ($state === 'present') return Color::Green;

                        // Use the enum's color for scores
                        $scores = collect(MemorizationScore::cases())->pluck('value');
                        if ($scores->contains($state)) {
                            return MemorizationScore::from($state)->getColor();
                        }

                        return Color::Gray;
                    })
                    ->icon(function ($state) {
                        if ($state === null) return 'heroicon-o-minus-circle';
                        if ($state === 'absent') return 'heroicon-o-x-circle';
                        if ($state === 'present') return 'heroicon-o-check-circle';

                        // Use the enum's icon for scores
                        $scores = collect(MemorizationScore::cases())->pluck('value');
                        if ($scores->contains($state)) {
                            return MemorizationScore::from($state)->getIcon();
                        }

                        return null;
                    })
                    ->iconPosition('before')
                    ->action(
                        Action::make('view_details_' . $formattedDate)
                            ->modalHeading(fn($record) => "تفاصيل يوم {$day} للطالب {$record->name}")
                            ->hidden(fn($record) => !$record->attendances()->whereDate('date', $formattedDate)->exists())
                            ->modalContent(function ($record) use ($formattedDate) {
                                $attendance = $record->attendances()
                                    ->whereDate('date', $formattedDate)
                                    ->first();

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
                    ->getStateUsing(function ($record) {
                        $number = $this->getTable()->getQuery()->get()->search(fn($memorizer) => $memorizer->id == $record->id) + 1;
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
                    ->withCount(['attendances as attendance_count' => function ($query) use ($dateFrom, $dateTo) {
                        $query->whereBetween('date', [$dateFrom, $dateTo]);
                    }])
                    ->orderByDesc('attendance_count');
            })
            ->paginated(false)
            ->recordUrl(null)
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }

    public function isReadOnly(): bool
    {
        return !$this->ownerRecord->managers->contains(auth()->user());
    }
}
