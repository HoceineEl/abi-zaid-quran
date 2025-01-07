<?php

namespace App\Filament\Association\Resources;

use App\Enums\Days;
use App\Filament\Association\Resources\GroupResource\Pages;
use App\Filament\Association\Resources\GroupResource\RelationManagers\AttendancesRelationManager;
use App\Filament\Association\Resources\GroupResource\RelationManagers\AttendancesScoreRelationManager;
use App\Filament\Association\Resources\GroupResource\RelationManagers\MemorizersRelationManager;
use App\Filament\Association\Resources\GroupResource\RelationManagers\PaymentsRelationManager;
use App\Filament\Association\Resources\GroupResource\RelationManagers\AttendanceTeacherRelationManager;
use App\Models\MemoGroup;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Form;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use App\Models\User;

class GroupResource extends Resource
{
    protected static ?string $model = MemoGroup::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'المجموعات';

    protected static ?string $modelLabel = 'مجموعة';

    protected static ?string $pluralModelLabel = 'المجموعات';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->label('الإسم')
                    ->required(),
                TextInput::make('price')
                    ->label('الثمن الذي تدفع هذه المجموعة ')
                    ->suffix('درهم')
                    ->default(100)
                    ->required(),
                Select::make('teacher_id')
                    ->options(fn() => User::where('role', 'teacher')->pluck('name', 'id'))
                    ->label('المدرس')
                    ->searchable()
                    ->preload(),
                ToggleButtons::make('days')
                    ->multiple()
                    ->inline()
                    ->options(Days::class)
                    ->label('الأيام')
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                TextEntry::make('name')
                    ->label('الإسم'),
                TextEntry::make('teacher.name')
                    ->label('المدرس'),
                TextEntry::make('arabic_days')
                    ->label('الأيام')
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(query: function ($query, string $search) {
                        return $query->where(function ($query) use ($search) {
                            $query->where('name', 'like', '%' . $search . '%')
                                ->orWhereHas('memorizers', function ($query) use ($search) {
                                    $query->where('name', 'like', '%' . $search . '%');
                                });
                        });
                    })
                    ->badge()
                    ->label('الإسم')
                    ->sortable(),
                Tables\Columns\TextColumn::make('teacher.name')
                    ->searchable()
                    ->label('المدرس')
                    ->sortable(),
                Tables\Columns\TextColumn::make('arabic_days')
                    ->searchable(false)
                    ->label('الأيام')
                    ->sortable(false),

                Tables\Columns\TextColumn::make('memorizers_count')
                    ->searchable(false)
                    ->getStateUsing(fn($record) => $record->memorizers_count)
                    ->label('عدد الطلاب')
                    ->sortable(false),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->hidden(fn() => auth()->user()->isTeacher()),
                Tables\Actions\ViewAction::make(),
            ])
            ->recordUrl(fn($record) => GroupResource::getUrl('view', ['record' => $record->id]))
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->hidden(fn() => auth()->user()->isTeacher()),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        if (auth()->check() && auth()->user()->isTeacher()) {
            return [
                AttendanceTeacherRelationManager::class,
                AttendancesScoreRelationManager::class,
            ];
        }
        return [
            MemorizersRelationManager::class,
            AttendancesRelationManager::class,
            AttendancesScoreRelationManager::class,
            PaymentsRelationManager::class,
        ];
    }
    public static function getEloquentQuery(): Builder
    {
        if (auth()->user()->isTeacher()) {
            $today = strtolower(now()->format('l')); // Get current day name in lowercase

            return parent::getEloquentQuery()
                ->where(function ($query) use ($today) {
                    $query->where('teacher_id', auth()->user()->id)
                        ->whereJsonContains('days', $today);
                });
        }
        return parent::getEloquentQuery();
    }
    public static function getPages(): array
    {
        if (auth()->check() && auth()->user()->isTeacher()) {
            return [
                'index' => Pages\ListGroups::route('/'),
                'view' => Pages\ViewGroup::route('/{record}'),
            ];
        }
        return [
            'index' => Pages\ListGroups::route('/'),
            'create' => Pages\CreateGroup::route('/create'),
            'edit' => Pages\EditGroup::route('/{record}/edit'),
            'view' => Pages\ViewGroup::route('/{record}'),
        ];
    }
}
