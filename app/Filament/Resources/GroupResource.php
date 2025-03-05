<?php

namespace App\Filament\Resources;

use App\Classes\Core;
use App\Filament\Resources\GroupResource\Pages;
use App\Filament\Resources\GroupResource\RelationManagers\ManagersRelationManager;
use App\Filament\Resources\GroupResource\RelationManagers\ProgressesRelationManager;
use App\Filament\Resources\GroupResource\RelationManagers\StudentsRelationManager;
use App\Models\Group;
use App\Models\Message;
use App\Models\Student;
use Filament\Forms;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action as ActionsAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class GroupResource extends Resource
{
    protected static ?string $model = Group::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'المجموعات';

    protected static ?string $modelLabel = 'مجموعة';

    protected static ?string $pluralModelLabel = 'مجموعات';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('الاسم')
                    ->required(),
                Grid::make(3)
                    ->schema([
                        Toggle::make('custom_type')
                            ->label('نوع  خاص')
                            ->formatStateUsing(function ($record) {
                                if ($record?->type === 'half_page' || $record?->type === 'two_lines') {
                                    return false;
                                }

                                return true;
                            })
                            ->dehydrated(false)
                            ->reactive()
                            ->default(false),
                        Forms\Components\ToggleButtons::make('type')
                            ->label('نوع المجموعة')
                            ->inline()
                            ->hidden(fn(Get $get) => $get('custom_type') === true)
                            ->options([
                                'two_lines' => 'سطران',
                                'half_page' => 'نصف صفحة',
                            ]),

                        TextInput::make('type')
                            ->label('نوع المجموعة')
                            ->reactive()
                            ->hidden(fn(Get $get) => $get('custom_type') === false),
                        Toggle::make('is_onsite')
                            ->label('مجموعة الحصة الحضورية')
                            ->default(false),
                    ]),
                Forms\Components\Select::make('message_id')
                    ->label('قالب الرسائل')
                    ->relationship('message', 'name')
                    ->preload()
                    ->searchable()
                    ->createOptionForm([
                        Forms\Components\TextInput::make('name')
                            ->label('اسم القالب')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Textarea::make('content')
                            ->label('محتوى الرسالة')
                            ->required()
                            ->helperText('يمكنك استخدام المتغيرات التالية: {student_name}, {group_name}, {curr_date}')
                            ->columnSpanFull(),
                    ])
                    ->createOptionAction(function (Forms\Components\Actions\Action $action) {
                        return $action
                            ->modalHeading('إنشاء قالب رسالة جديد')
                            ->modalSubmitActionLabel('إنشاء')
                            ->modalWidth('lg');
                    }),
            ])
            ->disabled(! Core::canChange());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('الاسم')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('نوع الحفظ')
                    ->formatStateUsing(
                        function ($state) {
                            return match ($state) {
                                'two_lines' => 'سطران',
                                'half_page' => 'نصف صفحة',
                                default => $state,
                            };
                        },
                    )
                    ->sortable(),
                Tables\Columns\TextColumn::make('managers.name')
                    ->label('المشرفون')
                    ->badge(),
                TextColumn::make('created_at')->label('تاريخ الإنشاء')
                    ->date('Y-m-d H:i:s'),
                TextColumn::make('manager_has_set_progress')
                    ->label('تم تسجيل الحضور؟')
                    ->getStateUsing(function (Group $record) {
                        $students = $record->students;
                        $progresses = $students->map(function ($student) {
                            return $student->progresses->where('date', now()->format('Y-m-d'))->count();
                        });

                        return $progresses->sum() . '/' . $students->count() . ' من الطلاب مسجلين اليوم';
                    })
                    ->searchable(false),
            ])
            ->filters([
                //
            ])
            ->recordUrl(fn(Group $record) => GroupResource::getUrl('edit', ['record' => $record, 'activeRelationManager' => 0]))
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('send_whatsapp_group')
                    ->label('أرسل رسالة للغائبين')
                    ->icon('heroicon-o-users')
                    ->color('info')
                    ->action(function (Group $record) {
                        Core::sendMessageToAbsence($record);
                    }),
                Tables\Actions\Action::make('remind_manager')
                    ->label('تذكير المشرفين ')
                    ->icon('heroicon-o-bell')
                    ->visible(function (Group $record) {
                        $one = auth()->user()->role === 'admin';
                        $students = $record->students;
                        $progresses = $students->map(function ($student) {
                            return $student->progresses->where('date', now()->format('Y-m-d'))->count();
                        });
                        $two = $progresses->sum() < $students->count();

                        return $one && $two;
                    })
                    ->color('primary')
                    ->form([
                        Textarea::make('message')
                            ->label('الرسالة')
                            ->default('من فضلكم قوموا بتسجيل الحضور للطلاب اليوم.')
                            ->rows(10)
                            ->required(),
                    ])
                    ->action(function (array $data, Group $record) {
                        $data['message'] = $data['message'] ?? 'من فضلكم قوموا بتسجيل الحضور للطلاب اليوم.';
                        $data['students'] = $record->managers()->pluck('id')->toArray();
                        $data['message_type'] = 'custom';
                        Core::sendMessageToSpecific($data, 'manager');
                    }),
            ])
            ->headerActions([
                ActionsAction::make('send_whatsapp')
                    ->label('أرسل رسالة للغائبين')
                    ->icon('heroicon-o-users')
                    ->visible(fn() => auth()->user()->role === 'admin')
                    ->action(function () {
                        Core::sendMessageToAbsence();
                    }),
                ActionsAction::make('send_to_specific')
                    ->color('info')
                    ->icon('heroicon-o-cube')
                    ->visible(fn() => auth()->user()->role === 'admin')
                    ->label('أرسل لطلبة محددين')
                    ->form([
                        Select::make('students')
                            ->label('الطلبة')
                            ->options(Student::pluck('name', 'id')->toArray())
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
                                FormAction::make('create')
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
                        Core::sendMessageToSpecific($data);
                    }),
            ])
            ->modifyQueryUsing(function ($query) {
                if (auth()->user()->role !== 'admin') {
                    $query->whereHas('managers', function ($query) {
                        $query->where('manager_id', auth()->id());
                    });
                } else {
                }
            })
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->disabled(! Core::canChange()),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            StudentsRelationManager::class,
            ProgressesRelationManager::class,
            ManagersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGroups::route('/'),
            'create' => Pages\CreateGroup::route('/create'),
            'edit' => Pages\EditGroup::route('/{record}/edit'),
            'view' => Pages\ViewGroup::route('/{record}'),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }
}
