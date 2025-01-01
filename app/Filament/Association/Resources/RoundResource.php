<?php

namespace App\Filament\Association\Resources;

use App\Enums\Days;
use App\Filament\Association\Resources\RoundResource\Pages;
use App\Models\Round;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use App\Filament\Association\Resources\RoundResource\RelationManagers\MemorizersRelationManager;

class RoundResource extends Resource
{
    protected static ?string $model = Round::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationLabel = 'الحلقات';

    protected static ?string $modelLabel = 'حلقة';

    protected static ?string $pluralModelLabel = 'الحلقات';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make()
                    ->schema([
                        TextInput::make('name')
                            ->label('اسم الحلقة')
                            ->required(),
                        CheckboxList::make('days')
                            ->label('أيام الحلقة')
                            ->options(Days::class)
                            ->columns(3)
                            ->required(),
                    ])->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('اسم الحلقة')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('days')
                    ->badge()
                    ->getStateUsing(
                        function ($record) {
                            return collect($record->days)
                                ->map(fn($day) => Days::from($day)->getLabel());
                        }
                    )
                    ->label('أيام الحلقة'),
                // ->label('أيام الحلقة')
                // ->formatStateUsing(fn($state) => dd($state) && collect($state)
                //     ->map(fn($day) => Days::from($day)->getLabel())
                //     ->join('، ')),
                TextColumn::make('memorizers_count')
                    ->label('عدد الطلاب')
                    ->counts('memorizers')
                    ->sortable(),
            ])
            ->filters([
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

    public static function getRelations(): array
    {
        return [
            MemorizersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRounds::route('/'),
            'create' => Pages\CreateRound::route('/create'),
            'edit' => Pages\EditRound::route('/{record}/edit'),
        ];
    }
}
