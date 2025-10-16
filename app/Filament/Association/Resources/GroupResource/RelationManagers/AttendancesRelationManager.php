<?php

namespace App\Filament\Association\Resources\GroupResource\RelationManagers;

use Filament\Schemas\Schema;
use DatePeriod;
use DateTime;
use DateInterval;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\IconSize;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AttendancesRelationManager extends RelationManager
{
    protected static string $relationship = 'memorizers';

    protected static ?string $title = 'الحضور';
    protected static ?string $navigationLabel = 'الحضور';
    protected static ?string $modelLabel = 'حضور';
    protected static ?string $pluralModelLabel = 'الحضور';

    public $dateFrom;
    public $dateTo;
    protected function canView(Model $record): bool
    {
        return !auth()->user()->isTeacher();
    }
    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        $this->dateFrom = $this->dateFrom ?? now()->subDays(4)->format('Y-m-d');
        $this->dateTo = $this->dateTo ?? now()->format('Y-m-d');

        $dateRange = new DatePeriod(
            new DateTime($this->dateFrom),
            new DateInterval('P1D'),
            (new DateTime($this->dateTo))->modify('+1 day')
        );

        $attendanceColumns = collect();
        foreach ($dateRange as $date) {
            $formattedDate = $date->format('Y-m-d H:i:s');
            $day = $date->format('d/m');
            $attendanceColumns->push(
                IconColumn::make("attendance_day_{$formattedDate}")
                    ->label($day)
                    ->state(function ($record) use ($formattedDate) {
                        $attendance = $record->attendances()
                            ->whereDate('date', $formattedDate)
                            ->first();

                        if (!$attendance) {
                            return 'none';
                        }

                        return $attendance->check_in_time ? 'present' : 'absent';
                    })
                    ->icon(fn(string $state): string => match ($state) {
                        'present' => 'heroicon-o-check-circle',
                        'absent' => 'heroicon-o-x-circle',
                        default => 'heroicon-o-minus-circle',
                    })
                    ->size(IconSize::ExtraLarge)
                    ->color(fn(string $state): string => match ($state) {
                        'present' => 'success',
                        'absent' => 'danger',
                        default => 'secondary',
                    })
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
                    ->columnSpan(4)
                    ->columns()
                    ->schema([
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
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }

    public function isReadOnly(): bool
    {
        return !$this->ownerRecord->managers->contains(auth()->user());
    }
}
