<?php

namespace App\Filament\Association\Resources\GroupResource\RelationManagers;

use Filament\Schemas\Schema;
use DatePeriod;
use DateTime;
use DateInterval;
use Filament\Actions\Action;
use Filament\Support\Enums\Size;
use App\Enums\AttendanceStatus;
use App\Enums\MemorizationScore;
use App\Models\Attendance;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AttendancesScoreRelationManager extends RelationManager
{
    protected static string $relationship = 'memorizers';
    protected static ?string $title = 'الحضور والتقييم';
    protected static ?string $navigationLabel = 'الحضور والتقييم';
    protected static ?string $modelLabel = 'حضور وتقييم';
    protected static ?string $pluralModelLabel = 'الحضور والتقييم';
    protected static string | \BackedEnum | null $icon = 'heroicon-o-clipboard-document-check';

    public $dateFrom;
    public $dateTo;

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
        $this->dateFrom = $this->dateFrom ?? $this->getDefaultDateFrom();
        $this->dateTo = $this->dateTo ?? now()->format('Y-m-d');

        $workingDays = $this->ownerRecord->days ?? [];

        $fullRange = new DatePeriod(
            new DateTime($this->dateFrom),
            new DateInterval('P1D'),
            (new DateTime($this->dateTo))->modify('+1 day')
        );

        $dateRange = $workingDays
            ? collect($fullRange)->filter(fn ($date) => in_array(strtolower($date->format('l')), $workingDays))
            : collect($fullRange);

        $attendanceColumns = collect();

        foreach ($dateRange as $date) {
            $formattedDate = $date->format('Y-m-d');
            $day = $date->format('d/m');

            $attendanceColumns->push(
                $this->buildAttendanceDayColumn($formattedDate, $day)
            );
        }

        return $table
            ->deferFilters()
            ->columns([
                TextColumn::make('name')
                    ->label('الاسم')
                    ->getStateUsing(function ($record, $rowLoop) {
                        return $rowLoop->iteration . '. ' . $record->name;
                    })
                    ->sortable(),
                ...$attendanceColumns->toArray(),
            ])
            ->filters([
                Filter::make('date')
                    ->columnSpanFull()
                    ->schema([
                        DatePicker::make('date_from')
                            ->label('من تاريخ')
                            ->reactive()
                            ->afterStateUpdated(fn ($state) => $this->dateFrom = $state ?? $this->getDefaultDateFrom())
                            ->default(fn () => $this->getDefaultDateFrom()),
                        DatePicker::make('date_to')
                            ->reactive()
                            ->label('إلى تاريخ')
                            ->afterStateUpdated(fn ($state) => $this->dateTo = $state ?? now()->format('Y-m-d'))
                            ->default(now()->format('Y-m-d')),
                    ], FiltersLayout::AboveContent),
            ])
            ->query(function () {
                $dateFrom = $this->dateFrom;
                $dateTo = $this->dateTo;

                return $this->ownerRecord->memorizers()
                    ->select('id', 'name')
                    ->with(['attendances' => function ($query) use ($dateFrom, $dateTo) {
                        $query
                            ->select('id', 'memorizer_id', 'date', 'check_in_time', 'score', 'notes', 'custom_note', 'absence_justified')
                            ->whereBetween('date', [$dateFrom, $dateTo]);
                    }])
                    ->withCount(['attendances as attendance_count' => function ($query) use ($dateFrom, $dateTo) {
                        $query->whereBetween('date', [$dateFrom, $dateTo]);
                    }])
                    ->orderByDesc('attendance_count');
            })
            ->paginated(false)
            ->recordUrl(null)
            ->headerActions([
                Action::make('export_table')
                    ->label('إرسال التقرير اليومي')
                    ->icon('heroicon-o-share')
                    ->size(Size::Small)
                    ->color('success')
                    ->action(function () {
                        $date = now()->format('Y-m-d');

                        $memorizers = $this->ownerRecord->memorizers()->get();

                        $html = view('components.attendance-export-table', [
                            'memorizers' => $memorizers,
                            'group' => $this->ownerRecord,
                            'date' => $date,
                        ])->render();

                        $this->dispatch('export-table', [
                            'html' => $html,
                            'groupName' => $this->ownerRecord->name,
                        ]);
                    }),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }

    public function isReadOnly(): bool
    {
        return ! $this->ownerRecord->managers->contains(auth()->user());
    }

    private function getDefaultDateFrom(int $sessions = 4): string
    {
        $workingDays = $this->ownerRecord->days ?? [];

        if (empty($workingDays)) {
            return now()->subWeeks(2)->format('Y-m-d');
        }

        $count = 0;
        $date = now()->copy();

        while (true) {
            if (in_array(strtolower($date->format('l')), $workingDays)) {
                $count++;
                if ($count === $sessions) {
                    return $date->format('Y-m-d');
                }
            }
            $date->subDay();
        }
    }

    // ─── Column Builder ────────────────────────────────────────────────

    /**
     * Build a single day's attendance column using AttendanceStatus as the source of truth.
     */
    private function buildAttendanceDayColumn(string $formattedDate, string $day): TextColumn
    {
        return TextColumn::make("attendance_day_{$formattedDate}")
            ->label($day)
            ->alignCenter()
            ->state(function ($record) use ($formattedDate) {
                $attendance = $this->findAttendanceForDate($record, $formattedDate);

                return AttendanceStatus::resolveDisplayState($attendance);
            })
            ->badge()
            ->formatStateUsing(fn (string $state): string => AttendanceStatus::getDisplayLabel($state))
            ->color(fn (string $state): string|array|null => AttendanceStatus::getDisplayColor($state))
            ->icon(fn (string $state): ?string => AttendanceStatus::getDisplayIcon($state))
            ->iconPosition('before')
            ->description(function ($record) use ($formattedDate) {
                $attendance = $this->findAttendanceForDate($record, $formattedDate);

                if ($attendance && ($attendance->notes || $attendance->custom_note)) {
                    return 'مشكلة';
                }

                return null;
            })
            ->action(
                Action::make('view_details_' . $formattedDate)
                    ->modalHeading(fn ($record) => "تفاصيل يوم {$day} للطالب {$record->name}")
                    ->hidden(fn ($record) => ! $this->findAttendanceForDate($record, $formattedDate))
                    ->modalContent(function ($record) use ($formattedDate) {
                        $attendance = $this->findAttendanceForDate($record, $formattedDate);

                        return view('filament.components.attendance-details', [
                            'attendance' => $attendance,
                            'memorizer' => $record,
                            'date' => $formattedDate,
                        ]);
                    })
                    ->modalWidth('2xl')
                    ->slideOver()
            );
    }

    /**
     * Find an attendance record for a given memorizer and date from the eager-loaded relation.
     */
    private function findAttendanceForDate($record, string $formattedDate): ?Attendance
    {
        return $record->attendances->first(
            fn (Attendance $attendance) => $attendance->date->format('Y-m-d') === $formattedDate
        );
    }
}
