<?php

namespace App\Filament\Association\Resources;

use App\Filament\Association\Resources\PaymentResource\Pages;
use App\Models\Payment;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use GuzzleHttp\Promise\Create;

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
                    ->required(),
                TextInput::make('amount')
                    ->label('المبلغ')
                    ->default(0)
                    ->required(),
                DatePicker::make('payment_date')
                    ->default(now())
                    ->label('تاريخ الدفع')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('memorizer.name')
                    ->label('الطالب'),
                TextColumn::make('amount')
                    ->label('المبلغ'),
                TextColumn::make('payment_date')
                    ->label('تاريخ الدفع'),
            ])
            ->filters([
                //
            ])

            ->actions([
                Tables\Actions\EditAction::make()->slideOver(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
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
            'edit' => Pages\EditPayment::route('/{record}/edit'),
        ];
    }
}
