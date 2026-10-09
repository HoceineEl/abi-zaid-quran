<?php

namespace App\Filament\Association\Resources\MemorizerResource\RelationManagers;

use App\Enums\PaymentType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $label = 'الدفعات';

    protected static ?string $title = 'الدفعات';

    protected static ?string $pluralTitle = 'الدفعات';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('type')
                    ->label('نوع الواجب')
                    ->options(PaymentType::class)
                    ->default(PaymentType::MONTHLY)
                    ->native(false)
                    ->required(),
                Forms\Components\TextInput::make('amount')
                    ->label('المبلغ')
                    ->numeric()
                    ->required(),
                Forms\Components\DatePicker::make('payment_date')
                    ->label('تاريخ الدفع')
                    ->default(now())
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('payment_date')
            ->modelLabel('دفعة')
            ->heading('الدفعات')
            ->pluralModelLabel('الدفعات')
            ->columns([
                Tables\Columns\TextColumn::make('type')
                    ->label('نوع الواجب')
                    ->badge(),
                Tables\Columns\TextColumn::make('amount')
                    ->label('المبلغ')
                    ->suffix(' درهم'),
                Tables\Columns\TextColumn::make('payment_date')
                    ->label('تاريخ الدفع')
                    ->date('Y-m-d'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
