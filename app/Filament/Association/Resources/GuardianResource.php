<?php

namespace App\Filament\Association\Resources;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Association\Resources\GuardianResource\Pages\ListGuardians;
use App\Filament\Association\Resources\GuardianResource\Pages\CreateGuardian;
use App\Filament\Association\Resources\GuardianResource\Pages\EditGuardian;
use App\Filament\Association\Resources\GuardianResource\Pages;
use App\Models\Guardian;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use App\Filament\Association\Resources\GuardianResource\RelationManagers\MemorizersRelationManager;

class GuardianResource extends Resource
{
    protected static ?string $model = Guardian::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-users';
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationLabel = 'أولياء الأمور';

    protected static ?string $modelLabel = 'ولي الأمر';

    protected static ?string $pluralModelLabel = 'أولياء الأمور';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        TextInput::make('name')
                            ->label('الإسم')
                            ->required(),
                        TextInput::make('phone')
                            ->label('الهاتف')
                            ->required(),
                        TextInput::make('address')
                            ->label('العنوان'),
                        TextInput::make('city')
                            ->label('المدينة')
                            ->default('أسفي'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('الإسم')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('phone')
                    ->label('الهاتف')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('address')
                    ->label('العنوان')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('city')
                    ->label('المدينة')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('memorizers_count')
                    ->label('عدد الطلاب')
                    ->counts('memorizers')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
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
            'index' => ListGuardians::route('/'),
            'create' => CreateGuardian::route('/create'),
            'edit' => EditGuardian::route('/{record}/edit'),
        ];
    }
}
