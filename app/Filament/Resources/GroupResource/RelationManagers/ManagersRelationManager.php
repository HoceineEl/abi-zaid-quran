<?php

namespace App\Filament\Resources\GroupResource\RelationManagers;

use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ManagersRelationManager extends RelationManager
{
    protected static string $relationship = 'managers';

    protected static bool $isLazy = false;

    protected static ?string $title = 'المشرفون';

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $modelLabel = 'مشرف';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label('الاسم'),
                TextColumn::make('email')->label('البريد الإلكتروني'),
                TextColumn::make('role')->label('الدور')
                    ->formatStateUsing(function ($state) {
                        return match ($state) {
                            'admin' => 'مشرف',
                            'follower' => 'متابع',
                            'teacher' => 'أستاذ بالجمعية',
                        };
                    }),
                TextColumn::make('phone')->label('الهاتف'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                AttachAction::make()
                    ->preloadRecordSelect()
                    ->multiple(),
            ])
            ->emptyStateHeading(fn () => 'لم يتم إضافة مشرفين بعد.')
            ->recordActions([
                EditAction::make(),
                DetachAction::make(),
            ])
            ->inverseRelationship('managedGroups')
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make(),
                ]),
            ]);
    }

    public function isReadOnly(): bool
    {
        return auth()->user()->role === 'follower';
    }
}
