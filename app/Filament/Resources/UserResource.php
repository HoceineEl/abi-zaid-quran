<?php

namespace App\Filament\Resources;

use App\Classes\Core;
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
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'المستخدمين';

    protected static ?string $modelLabel = 'مستخدم';

    protected static ?string $pluralModelLabel = 'مستخدمين';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('الاسم')
                    ->required(),
                Forms\Components\TextInput::make('email')
                    ->label('البريد الإلكتروني')
                    ->email()
                    ->required(),
                Forms\Components\Select::make('role')
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
                Forms\Components\TextInput::make('phone')
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
                Action::make('send_to_specific')
                    ->color('info')
                    ->icon('heroicon-o-cube')
                    ->label('أرسل إلى مستخدمين محدد')
                    ->form([
                        Select::make('students')
                            ->label('الطلبة')
                            ->options(User::pluck('name', 'id')->toArray())
                            ->multiple()
                            ->required(),
                        ToggleButtons::make('message_type')
                            ->label('نوع الرسالة')
                            ->options([
                                'message' => 'قالب رسالة',
                                'custom' => 'رسالة مخصصة',
                            ])
                            ->reactive()
                            ->default('message')
                            ->inline(),
                        Select::make('message')
                            ->label('الرسالة')
                            ->native()
                            ->hidden(fn(Get $get) => $get('message_type') === 'custom')
                            ->options(Message::pluck('name', 'id')->toArray())
                            ->hintActions([
                                ActionsAction::make('create')
                                    ->label('إنشاء قالب')
                                    ->slideOver()
                                    ->modalWidth('4xl')
                                    ->icon('heroicon-o-plus-circle')
                                    ->form([
                                        TextInput::make('name')
                                            ->label('اسم القالب')
                                            ->required(),
                                        Textarea::make('content')
                                            ->label('الرسالة')
                                            ->rows(10)
                                            ->required(),
                                    ])
                                    ->action(function (array $data) {
                                        Message::create($data);

                                        Notification::make()
                                            ->title('تم إنشاء قالب الرسالة')
                                            ->color('success')
                                            ->icon('heroicon-o-check-circle')
                                            ->send();
                                    }),
                            ])
                            ->required(),
                        Textarea::make('message')
                            ->label('الرسالة')
                            ->hidden(fn(Get $get) => $get('message_type') !== 'custom')
                            ->rows(10)
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        Core::sendMessageToSpecific($data, 'user');
                    }),
            ])
            ->actions([
                Action::make('attach_group')
                    ->label('إرفاق مجموعة')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->form(fn(User $record) => [
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
                Action::make('detach_group')
                    ->label('إزالة مجموعة')
                    ->icon('heroicon-o-minus-circle')
                    ->color('danger')
                    ->visible(fn(User $record) => $record->managedGroups()->exists())
                    ->form(fn(User $record) => [
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
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
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
