<?php

namespace App\Filament\Association\Resources\GroupResource\RelationManagers;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Carbon\Carbon;
use Filament\Tables\Columns\IconColumn\IconColumnSize;
use Filament\Tables\Enums\FiltersLayout;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'memorizers';

    protected static ?string $title = 'المدفوعات';
    protected static ?string $navigationLabel = 'المدفوعات';
    protected static ?string $modelLabel = 'دفعة';
    protected static ?string $pluralModelLabel = 'المدفوعات';

    public ?string $dateFrom = null;
    public ?string $dateTo = null;
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
        $this->dateFrom = $this->dateFrom ?? Carbon::now()->startOfYear()->toDateString();
        $this->dateTo = $this->dateTo ?? Carbon::now()->startOfYear()->addMonths(2)->toDateString();

        $paymentColumns = $this->generatePaymentColumns();

        return $table
            ->deferFilters()
            ->columns([
                TextColumn::make('name')
                    ->label('الاسم')
                    ->state(function ($record) {
                        $number = $this->ownerRecord->memorizers->search(fn($memorizer) => $memorizer->id == $record->id) + 1;
                        return $number . '. ' . $record->name;
                    })
                    ->sortable(),
                ...$paymentColumns,
            ])
            ->filters([
                $this->getDateFilter(),
            ], layout: FiltersLayout::AboveContent)
            ->query(function () {
                return $this->getQuery();
            })
            ->paginated(false)
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }

    private function generatePaymentColumns(): array
    {
        $arabicMonths = [
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
            'ديسمبر'
        ];

        return collect(Carbon::parse($this->dateFrom)->monthsUntil($this->dateTo))
            ->map(function (Carbon $date) use ($arabicMonths) {
                $formattedDate = $date->format('Y-m');
                $arabicMonthName = $arabicMonths[$date->month - 1];

                return IconColumn::make("payment_month_{$formattedDate}")
                    ->label($arabicMonthName)
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->size(IconColumnSize::ExtraLarge)
                    ->state(function ($record) use ($date) {
                        return $record->payments()
                            ->whereYear('payment_date', $date->year)
                            ->whereMonth('payment_date', $date->month)
                            ->exists();
                    });
            })->toArray();
    }

    private function getDateFilter(): Filter
    {
        return Filter::make('date')
            ->columnSpan(4)
            ->columns()
            ->form([
                DatePicker::make('date_from')
                    ->label('من تاريخ')
                    ->reactive()
                    ->afterStateUpdated(fn($state) => $this->dateFrom = $state ?? Carbon::now()->startOfYear()->toDateString())
                    ->default(Carbon::now()->startOfYear()),
                DatePicker::make('date_to')
                    ->reactive()
                    ->label('إلى تاريخ')
                    ->afterStateUpdated(fn($state) => $this->dateTo = $state ?? Carbon::now()->endOfYear()->toDateString())
                    ->default(Carbon::now()->endOfYear()),
            ]);
    }

    private function getQuery()
    {
        return $this->ownerRecord->memorizers()
            ->withCount(['payments as payment_count' => function ($query) {
                $query->whereBetween('payment_date', [$this->dateFrom, $this->dateTo]);
            }])
            ->orderByDesc('payment_count');
    }

    public function isReadOnly(): bool
    {
        return !$this->ownerRecord->managers->contains(auth()->user());
    }
}
