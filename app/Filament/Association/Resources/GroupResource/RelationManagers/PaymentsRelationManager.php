<?php

namespace App\Filament\Association\Resources\GroupResource\RelationManagers;

use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Enums\IconSize;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'memorizers';

    protected static ?string $title = 'المدفوعات';

    protected static ?string $navigationLabel = 'المدفوعات';

    protected static ?string $modelLabel = 'دفعة';

    protected static ?string $pluralModelLabel = 'المدفوعات';

    private const ARABIC_MONTHS = [
        'يناير',
        'فبراير',
        'مارس',
        'أبريل',
        'مايو',
        'يونيو',
        'يوليو',
        'أغسطس',
        'سبتمبر',
        'أكتوبر',
        'نوفمبر',
        'ديسمبر',
    ];

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
        $this->dateFrom ??= Carbon::now()->startOfYear()->toDateString();
        $this->dateTo ??= Carbon::now()->startOfYear()->addMonths(2)->toDateString();

        return $table
            ->deferFilters()
            ->columns([
                TextColumn::make('name')
                    ->label('الاسم')
                    ->state(function ($record) {
                        $number = $this->ownerRecord->memorizers->search(fn ($memorizer) => $memorizer->id == $record->id) + 1;

                        return $number.'. '.$record->name;
                    })
                    ->sortable(),
                ...$this->generatePaymentColumns(),
            ])
            ->filters([
                $this->getDateFilter(),
            ], layout: FiltersLayout::AboveContent)
            ->query(fn (): Builder => $this->getQuery())
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
    private function generatePaymentColumns(): array
    {
        return collect(Carbon::parse($this->dateFrom)->monthsUntil($this->dateTo))
            ->map(function (Carbon $date) {
                $formattedDate = $date->format('Y-m');
                $arabicMonthName = self::ARABIC_MONTHS[$date->month - 1];

                return IconColumn::make("payment_month_{$formattedDate}")
                    ->label($arabicMonthName)
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->size(IconSize::ExtraLarge)
                    ->state(fn ($record): bool => $record->payments()
                        ->whereYear('payment_date', $date->year)
                        ->whereMonth('payment_date', $date->month)
                        ->exists());
            })
            ->all();
    }

    private function getDateFilter(): Filter
    {
        return Filter::make('date')
            ->columnSpan(4)
            ->columns()
            ->schema([
                DatePicker::make('date_from')
                    ->label('من تاريخ')
                    ->reactive()
                    ->afterStateUpdated(fn ($state) => $this->dateFrom = $state ?? Carbon::now()->startOfYear()->toDateString())
                    ->default(Carbon::now()->startOfYear()),
                DatePicker::make('date_to')
                    ->label('إلى تاريخ')
                    ->reactive()
                    ->afterStateUpdated(fn ($state) => $this->dateTo = $state ?? Carbon::now()->endOfYear()->toDateString())
                    ->default(Carbon::now()->endOfYear()),
            ]);
    }

    private function getQuery(): Builder
    {
        return $this->ownerRecord->memorizers()
            ->withCount(['payments as payment_count' => fn ($query) => $query->whereBetween('payment_date', [$this->dateFrom, $this->dateTo])])
            ->orderByDesc('payment_count');
    }
}
