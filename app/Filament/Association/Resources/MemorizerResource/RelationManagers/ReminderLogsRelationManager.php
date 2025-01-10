<?php

namespace App\Filament\Association\Resources\MemorizerResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ReminderLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'reminderLogs';

    protected static ?string $title = 'سجل التذكيرات';

    protected static ?string $modelLabel = 'تذكير';

    protected static ?string $pluralModelLabel = 'التذكيرات';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('type')
                    ->label('النوع')
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'payment' => 'تذكير بالدفع',
                        'absence' => 'تذكير بالغياب',
                        'trouble' => 'تذكير بالشغب',
                        'no_memorization' => 'تذكير بعدم الحفظ',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('phone_number')
                    ->label('رقم الهاتف')
                    ->extraCellAttributes(['dir' => 'ltr'])
                    ->alignRight(),
                Tables\Columns\TextColumn::make('message')
                    ->label('الرسالة')
                    ->limit(50),
                Tables\Columns\IconColumn::make('is_parent')
                    ->label('ولي الأمر')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاريخ الإرسال')
                    ->dateTime('Y-m-d H:i:s'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                //
            ])
            ->actions([
                //
            ])
            ->bulkActions([
                //
            ]);
    }
}
