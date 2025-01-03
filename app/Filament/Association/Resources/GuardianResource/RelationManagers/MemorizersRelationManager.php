<?php

namespace App\Filament\Association\Resources\GuardianResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;

class MemorizersRelationManager extends RelationManager
{
    protected static string $relationship = 'memorizers';

    protected static ?string $title = 'الأبناء';

    protected static ?string $modelLabel = 'إبن';

    protected static ?string $pluralModelLabel = 'الأبناء';

    public function table(Table $table): Table
    {
        return $table
            ->pluralModelLabel('الأبناء')
            ->modelLabel('إبن')
            ->columns([
                TextColumn::make('name')
                    ->label('الإسم')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('phone')
                    ->label('الهاتف الخاص')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('تم نسخ الهاتف')
                    ->copyMessageDuration(1500),
                TextColumn::make('group.name')
                    ->label('المجموعة')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('round.name')
                    ->label('الحلقة')
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
