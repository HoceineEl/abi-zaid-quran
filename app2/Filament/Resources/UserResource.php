<?php

namespace App\Filament\Resources;

use App\Classes\Core;
use App\Filament\Resources\UserResource\Pages;
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
use Filament\Tables\Table;

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
                TextColumn::make('name')->label('الاسم'),
                TextColumn::make('email')->label('البريد الإلكتروني'),
                TextColumn::make('role')->label('الدور')
                    ->formatStateUsing(function ($state) {
                        return match ($state) {
                            'admin' => 'مشرف',
                            'follower' => 'متابع',
                        };
                    }),
                TextColumn::make('phone')->label('الهاتف'),
            ])
            ->filters([
                //
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
                            ->hidden(fn (Get $get) => $get('message_type') === 'custom')
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
                            ->hidden(fn (Get $get) => $get('message_type') !== 'custom')
                            ->rows(10)
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        Core::sendMessageToSpecific($data, 'user');
                    }),
            ])
            ->actions([
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
        return auth()->user()->role === 'admin';
    }
}
