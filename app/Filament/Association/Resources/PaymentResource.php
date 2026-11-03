<?php

namespace App\Filament\Association\Resources;

use App\Enums\PaymentType;
use App\Filament\Association\Resources\PaymentResource\Pages;
use App\Models\MemoGroup;
use App\Models\Memorizer;
use App\Models\Payment;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $modelLabel = 'دفعة';

    protected static ?string $pluralModelLabel = 'الدفعات';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('memorizer_id')
                    ->label('الطالب')
                    ->relationship('memorizer', 'name')
                    ->searchable()
                    ->preload()
                    ->reactive()
                    ->afterStateUpdated(fn (Set $set, $state) => $set('amount', Memorizer::find($state)?->group->price ?? 70))
                    ->required(),
                Select::make('type')
                    ->label('نوع الواجب')
                    ->options(PaymentType::class)
                    ->default(PaymentType::MONTHLY)
                    ->native(false)
                    ->required(),
                TextInput::make('amount')
                    ->label('المبلغ كامل')
                    ->default(70)
                    ->reactive()
                    ->required(),
                Repeater::make('payments')
                    ->label('الدفعات')
                    ->helperText('يمكنك إضافة أكثر من دفعة , لاضافة الدفع التراكمي')
                    ->live()
                    ->grid(2)
                    ->schema([
                        TextInput::make('paid_amount_month')
                            ->label('المبلغ المدفوع في هذه الدفعة')
                            ->default(function (Set $set, Get $get) {
                                $totalAmount = $get('../../amount') ?? 70;
                                $itemsCount = count($get('../../payments')) ?: 1;
                                $equalShare = round($totalAmount / $itemsCount, 2);
                                foreach ($get('../../payments') as $index => $payment) {
                                    $set("../../payments.{$index}.paid_amount_month", $equalShare);
                                }

                                return $equalShare;
                            })

                            ->required(),
                        DatePicker::make('payment_date')
                            ->default(function ($get) {
                                $count = count($get('../*.payment_date') ?? []) - 1;

                                return now()->subMonths($count);
                            })
                            ->label('تاريخ الدفع')
                            ->required(),
                    ])->cloneable(),

            ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('memorizer.name')
                    ->searchable()
                    ->sortable()
                    ->label('الطالب'),
                TextColumn::make('type')
                    ->label('نوع الواجب')
                    ->badge()
                    ->sortable(),
                TextColumn::make('amount')
                    ->numeric()
                    ->sortable()
                    ->label('المبلغ'),
                TextColumn::make('payment_date')
                    ->date('Y-m-d')
                    ->searchable()
                    ->sortable()
                    ->label('تاريخ الدفع'),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('نوع الواجب')
                    ->options(PaymentType::class)
                    ->multiple(),
                SelectFilter::make('memo_group_id')
                    ->label('المجموعة')
                    ->options(fn () => MemoGroup::orderBy('name')->pluck('name', 'id'))
                    ->multiple()
                    ->preload()
                    ->query(fn (Builder $query, array $data) => $query->when($data['values'], fn (Builder $query, array $groupIds) => $query->whereHas('memorizer', fn (Builder $query) => $query->whereIn('memo_group_id', $groupIds)))),
                SelectFilter::make('sex')
                    ->label('الجنس')
                    ->options(['male' => 'ذكور', 'female' => 'إناث'])
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'], fn (Builder $query, string $sex) => $query->whereHas('memorizer', fn (Builder $query) => $query->ofSex($sex)))),
                Filter::make('amount')
                    ->label('المبلغ')
                    ->form([
                        TextInput::make('min_amount')
                            ->label('المبلغ من')
                            ->numeric()
                            ->minValue(0),
                        TextInput::make('max_amount')
                            ->label('المبلغ إلى')
                            ->numeric()
                            ->minValue(0),
                    ])
                    ->columns(2)
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when(filled($data['min_amount'] ?? null), fn (Builder $q) => $q->where('amount', '>=', (float) $data['min_amount']))
                        ->when(filled($data['max_amount'] ?? null), fn (Builder $q) => $q->where('amount', '<=', (float) $data['max_amount'])))
                    ->indicateUsing(fn (array $data): array => array_values(array_filter([
                        filled($data['min_amount'] ?? null) ? 'المبلغ من '.$data['min_amount'] : null,
                        filled($data['max_amount'] ?? null) ? 'المبلغ إلى '.$data['max_amount'] : null,
                    ]))),
                Filter::make('payment_date')
                    ->label('تاريخ الدفع')
                    ->form([
                        DatePicker::make('from')
                            ->label('من تاريخ'),
                        DatePicker::make('until')
                            ->label('إلى تاريخ'),
                    ])
                    ->columns(2)
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $q, string $date) => $q->whereDate('payment_date', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $q, string $date) => $q->whereDate('payment_date', '<=', $date)))
                    ->indicateUsing(fn (array $data): array => array_values(array_filter([
                        filled($data['from'] ?? null) ? 'من '.$data['from'] : null,
                        filled($data['until'] ?? null) ? 'إلى '.$data['until'] : null,
                    ]))),
                SelectFilter::make('month')
                    ->options(function () {
                        return collect(range(1, 12))->mapWithKeys(function ($month) {
                            return [$month => now()->month($month)->translatedFormat('F')];
                        });
                    })
                    ->default(now()->month)
                    ->query(fn (Builder $query, $state) => $query->when($state['value'], fn ($query) => $query->whereMonth('payment_date', $state['value'])))
                    ->label('الشهر'),
                SelectFilter::make('year')
                    ->options(function () {
                        return collect(range(2020, now()->year))->mapWithKeys(function ($year) {
                            return [$year => $year];
                        });
                    })
                    ->default(now()->year)
                    ->query(fn (Builder $query, $state) => $query->when($state['value'], fn ($query) => $query->whereYear('payment_date', $state['value'])))
                    ->label('السنة'),
            ])
            ->actions([
                ActionGroup::make([
                    Tables\Actions\Action::make('show_receipt')
                        ->label('عرض الإيصال')
                        ->icon('heroicon-o-printer')
                        ->modalContent(fn (Payment $record) => view('filament.resources.payment-resource.pages.receipt-preview', [
                            'payments' => collect([$record]),
                        ]))
                        ->modalWidth('4xl')
                        ->slideOver(),
                    Tables\Actions\EditAction::make()
                        ->slideOver()
                        ->form([
                            Select::make('type')
                                ->label('نوع الواجب')
                                ->options(PaymentType::class)
                                ->native(false)
                                ->required(),
                            TextInput::make('amount')
                                ->label('المبلغ')
                                ->required(),
                            DatePicker::make('payment_date')
                                ->label('تاريخ الدفع')
                                ->required(),
                        ])
                        ->action(function (Payment $record, array $data) {
                            $record->update($data);
                            Notification::make()
                                ->title('تم تعديل الدفعة بنجاح')
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\DeleteAction::make(),
                ]),
            ])
            ->emptyStateHeading('لا توجد دفعات مسجلة للفترة المحددة في الفلتر')
            ->emptyStateDescription('يمكنك إضافة دفعات جديدة بالضغط على زر الإضافة')
            ->emptyStateIcon('heroicon-o-banknotes')
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('bulk_edit')
                        ->label('تعديل المحدد')
                        ->icon('heroicon-o-pencil-square')
                        ->modalDescription('اترك الحقل فارغًا إذا لم ترد تغييره.')
                        ->form([
                            Select::make('type')
                                ->label('نوع الواجب')
                                ->options(PaymentType::class)
                                ->native(false),
                            TextInput::make('amount')
                                ->label('المبلغ')
                                ->numeric()
                                ->minValue(0),
                            DatePicker::make('payment_date')
                                ->label('تاريخ الدفع'),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $changes = array_filter($data, 'filled');

                            if (! $changes) {
                                return;
                            }

                            $records->each(function (Payment $payment) use ($changes) {
                                $payment->update($changes);
                                $payment->memorizer?->clearPaymentCache();
                            });

                            Notification::make()
                                ->title("تم تعديل {$records->count()} دفعة بنجاح")
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayments::route('/'),
            'create' => Pages\CreatePayment::route('/create'),
        ];
    }
}
