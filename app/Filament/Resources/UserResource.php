<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use Filament\Actions\ActionGroup;
use Filament\Schemas\Components\Section;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Classes\Core;
use App\Filament\Actions\SendMessageToSelectedUsersAction;
use App\Filament\Resources\UserResource\Pages;
use App\Models\Group;
use App\Models\Message;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\Actions\Action as ActionsAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Filament\Infolists\Components\TextEntry;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'المستخدمين';

    protected static ?string $modelLabel = 'مستخدم';

    protected static ?string $pluralModelLabel = 'مستخدمين';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('الاسم')
                    ->required(),
                TextInput::make('email')
                    ->label('البريد الإلكتروني')
                    ->email()
                    ->required(),
                Select::make('role')
                    ->label('الدور')
                    ->options([
                        'admin' => 'مشرف',
                        'follower' => 'متابع',
                        'teacher' => 'أستاذ بالجمعية',
                    ])
                    ->required(),
                TextInput::make('password')
                    ->label('كلمة المرور')
                    ->password()
                    ->revealable()
                    ->required(),
                TextInput::make('phone')
                    ->label('رقم الهاتف')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('الاسم')
                    ->color('primary')
                    ->weight('bold')
                    ->searchable(),
                TextColumn::make('email')->label('البريد الإلكتروني')
                    ->color('gray')
                    ->icon('heroicon-o-envelope')
                    ->searchable(),
                TextColumn::make('role')->label('الدور')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'admin' => 'danger',
                        'follower' => 'warning',
                        'teacher' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(function ($state) {
                        return match ($state) {
                            'admin' => 'مشرف',
                            'follower' => 'متابع',
                            'teacher' => 'أستاذ بالجمعية',
                            default => $state,
                        };
                    })
                    ->searchable(),
                TextColumn::make('phone')->label('الهاتف')
                    ->color('info')
                    ->icon('heroicon-o-phone')
                    ->searchable(),
                TextColumn::make('managedGroups.name')
                    ->label('المجموعات')
                    ->listWithLineBreaks()
                    ->limitList(3)
                    ->expandableLimitedList()
                    ->badge()
                    ->color('success')
                    ->icon('heroicon-o-academic-cap'),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->label('الدور')
                    ->options([
                        'admin' => 'مشرف',
                        'follower' => 'متابع',
                    ]),
                SelectFilter::make('managedGroups')
                    ->label('المجموعة')
                    ->relationship('managedGroups', 'name')
                    ->multiple()
                    ->searchable(),
            ])
            ->headerActions([

            ])
            ->recordActions([
                EditAction::make(),
                ActionGroup::make([
                    \Filament\Actions\Action::make('view_groups')
                        ->label('عرض المجموعات')
                        ->icon('heroicon-o-academic-cap')
                        ->color('info')
                        ->modalHeading('المجموعات المرفقة')
                        ->modalWidth('xl')
                        ->schema([
                            Section::make('المجموعات المرفقة')
                                ->schema([
                                    TextEntry::make('managedGroups.name')
                                        ->label('المجموعات')
                                        ->listWithLineBreaks()
                                        ->badge()
                                        ->color('success')
                                        ->icon('heroicon-o-academic-cap'),
                                ])
                                ->columns(1),
                        ]),
                    \Filament\Actions\Action::make('attach_group')
                        ->label('إرفاق مجموعة')
                        ->icon('heroicon-o-plus-circle')
                        ->color('success')
                        ->schema(fn(User $record) => [
                            Select::make('group_ids')
                                ->label('المجموعات')
                                ->multiple()
                                ->options(
                                    Group::query()
                                        ->whereDoesntHave('managers', fn($q) => $q->where('users.id', $record->id))
                                        ->pluck('name', 'id')
                                        ->toArray()
                                )
                                ->searchable()
                                ->required(),
                        ])
                        ->action(function (User $record, array $data) {
                            $record->managedGroups()->syncWithoutDetaching($data['group_ids']);
                            Notification::make()
                                ->title('تم إرفاق المجموعات')
                                ->color('success')
                                ->icon('heroicon-o-check-circle')
                                ->send();
                        }),
                    \Filament\Actions\Action::make('detach_group')
                        ->label('إزالة مجموعة')
                        ->icon('heroicon-o-minus-circle')
                        ->color('danger')
                        ->visible(fn(User $record) => $record->managedGroups()->exists())
                        ->schema(fn(User $record) => [
                            Select::make('group_ids')
                                ->label('المجموعات')
                                ->multiple()
                                ->options($record->managedGroups()->pluck('name', 'id')->toArray())
                                ->searchable()
                                ->required(),
                        ])
                        ->action(function (User $record, array $data) {
                            $record->managedGroups()->detach($data['group_ids']);
                            Notification::make()
                                ->title('تمت إزالة المجموعات')
                                ->color('success')
                                ->icon('heroicon-o-check-circle')
                                ->send();
                        }),
                ])
                    ->label('المجموعات')
                    ->icon('heroicon-o-academic-cap'),
            ])
            ->toolbarActions([
                SendMessageToSelectedUsersAction::make(),
                DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getEloquentQuery(): Builder
    {

        return parent::getEloquentQuery()->where('role', '!=', 'teacher');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email', 'role', 'phone'];
    }

    public static function canAccess(): bool
    {
        return Auth::user()?->role === 'admin';
    }
}
