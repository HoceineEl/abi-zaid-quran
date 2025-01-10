<?php

namespace App\Filament\Association\Resources\MemorizerResource\RelationManagers;

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
                Forms\Components\TextInput::make('payment_date')
                    ->required()
                    ->maxLength(255),
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
                Tables\Columns\TextColumn::make('payment_date'),
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
