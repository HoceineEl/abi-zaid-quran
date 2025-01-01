<?php

namespace App\Filament\Association\Resources\RoundResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;

class MemorizersRelationManager extends RelationManager
{
    protected static string $relationship = 'memorizers';

    protected static ?string $title = 'الطلاب';

    protected static ?string $modelLabel = 'طالب';

    protected static ?string $pluralModelLabel = 'الطلاب';

    public function table(Table $table): Table
    {
        return $table
            ->pluralModelLabel('الطلاب')
            ->modelLabel('طالب')
            ->columns([
                TextColumn::make('name')
                    ->label('الإسم')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('display_phone')
                    ->label('الهاتف')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('تم نسخ الهاتف')
                    ->copyMessageDuration(1500),
                TextColumn::make('group.name')
                    ->label('المجموعة')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('teacher.name')
                    ->label('المعلم')
                    ->searchable()
                    ->sortable(),
                IconColumn::make('has_payment_this_month')
                    ->label('دفع هذا الشهر')
                    ->boolean(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
