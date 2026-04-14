<?php

namespace App\Filament\Association\Resources\GroupResource\RelationManagers;

use DateInterval;
use DatePeriod;
use DateTime;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Enums\IconSize;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class AttendancesRelationManager extends RelationManager
{
    protected static string $relationship = 'memorizers';

    protected static ?string $title = 'الحضور';

    protected static ?string $navigationLabel = 'الحضور';

    protected static ?string $modelLabel = 'حضور';

    protected static ?string $pluralModelLabel = 'الحضور';

    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    protected function canView(Model $record): bool
    {
        return ! auth()->user()->isTeacher();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        $this->dateFrom ??= $this->getDefaultDateFrom();
        $this->dateTo ??= now()->format('Y-m-d');

        return $table
            ->deferFilters()
            ->columns([
                TextColumn::make('name')
                    ->label('الاسم')
                    ->getStateUsing(function ($record) {
                        $number = $this->getTable()->getQuery()->get()->search(fn ($memorizer) => $memorizer->id == $record->id) + 1;

                        return $number.'. '.$record->name;
                    })
                    ->sortable(),
                ...$this->generateAttendanceColumns(),
            ])
            ->filters([
                Filter::make('date')
                    ->columnSpan(4)
                    ->columns()
                    ->schema([
                        DatePicker::make('date_from')
                            ->label('من تاريخ')
                            ->reactive()
                            ->afterStateUpdated(fn ($state) => $this->dateFrom = $state ?? $this->getDefaultDateFrom())
                            ->default(fn () => $this->getDefaultDateFrom()),
                        DatePicker::make('date_to')
                            ->label('إلى تاريخ')
                            ->reactive()
                            ->afterStateUpdated(fn ($state) => $this->dateTo = $state ?? now()->format('Y-m-d'))
                            ->default(now()->format('Y-m-d')),
                    ], FiltersLayout::AboveContent),
            ])
            ->query(fn () => $this->ownerRecord->memorizers()
                ->withCount(['attendances as attendance_count' => fn ($query) => $query->whereBetween('date', [$this->dateFrom, $this->dateTo])])
                ->orderByDesc('attendance_count'))
            ->paginated(false)
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }

    public function isReadOnly(): bool
    {
        return ! $this->ownerRecord->managers->contains(auth()->user());
    }

    /**
     * @return array<int, IconColumn>
     */
    private function generateAttendanceColumns(): array
    {
        $workingDays = $this->ownerRecord->days ?? [];

        $fullRange = new DatePeriod(
            new DateTime($this->dateFrom),
            new DateInterval('P1D'),
            (new DateTime($this->dateTo))->modify('+1 day')
        );

        $dateRange = $workingDays
            ? collect($fullRange)->filter(fn ($date) => in_array(strtolower($date->format('l')), $workingDays))
            : collect($fullRange);

        return $dateRange->map(function (DateTime $date) {
            $formattedDate = $date->format('Y-m-d H:i:s');

            return IconColumn::make("attendance_day_{$formattedDate}")
                ->label($date->format('d/m'))
                ->state(function ($record) use ($formattedDate) {
                    $attendance = $record->attendances()
                        ->whereDate('date', $formattedDate)
                        ->first();

                    if (! $attendance) {
                        return 'none';
                    }

                    return $attendance->check_in_time ? 'present' : 'absent';
                })
                ->icon(fn (string $state): string => match ($state) {
                    'present' => 'heroicon-o-check-circle',
                    'absent' => 'heroicon-o-x-circle',
                    default => 'heroicon-o-minus-circle',
                })
                ->color(fn (string $state): string => match ($state) {
                    'present' => 'success',
                    'absent' => 'danger',
                    default => 'secondary',
                })
                ->size(IconSize::ExtraLarge);
        })->values()->all();
    }

    private function getDefaultDateFrom(int $sessions = 4): string
    {
        $workingDays = $this->ownerRecord->days ?? [];

        if (empty($workingDays)) {
            return now()->subWeeks(2)->format('Y-m-d');
        }

        $count = 0;
        $date = now()->copy();

        while ($count < $sessions) {
            if (in_array(strtolower($date->format('l')), $workingDays)) {
                $count++;

                if ($count === $sessions) {
                    return $date->format('Y-m-d');
                }
            }

            $date->subDay();
        }

        return $date->format('Y-m-d');
    }
}
