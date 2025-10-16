<?php

namespace App\Filament\Association\Resources;

use App\Filament\Association\Resources\PaymentResource\Pages\CreatePayment;
use App\Filament\Association\Resources\PaymentResource\Pages\ListPayments;
use App\Models\Memorizer;
use App\Models\Payment;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Database\Eloquent\Builder;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $modelLabel = 'دفعة';

    protected static ?string $pluralModelLabel = 'الدفعات';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('memorizer_id')
                    ->label('الطالب')
                    ->relationship('memorizer', 'name')
                    ->searchable()
                    ->preload()
                    ->reactive()
                    ->afterStateUpdated(fn (Set $set, $state) => $set('amount', Memorizer::find($state)?->group->price ?? 70))
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
                TextColumn::make('amount')
                    ->numeric()
                    ->sortable()
                    ->label('المبلغ'),
                TextColumn::make('payment_date')
                    ->searchable()
                    ->sortable()
                    ->label('تاريخ الدفع'),
            ])
            ->filters([
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
            ->recordActions([
                ActionGroup::make([
                    Action::make('show_receipt')
                        ->label('عرض الإيصال')
                        ->icon('heroicon-o-printer')
                        ->modalContent(fn (Payment $record) => view('filament.resources.payment-resource.pages.receipt-preview', [
                            'payments' => collect([$record]),
                        ]))
                        ->modalWidth('4xl')
                        ->slideOver(),
                    EditAction::make()
                        ->slideOver()
                        ->schema([
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
                    DeleteAction::make(),
                ]),
            ])
            ->emptyStateHeading('لا توجد دفعات مسجلة للفترة المحددة في الفلتر')
            ->emptyStateDescription('يمكنك إضافة دفعات جديدة بالضغط على زر الإضافة')
            ->emptyStateIcon('heroicon-o-banknotes')
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
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
            'index' => ListPayments::route('/'),
            'create' => CreatePayment::route('/create'),
        ];
    }
}
