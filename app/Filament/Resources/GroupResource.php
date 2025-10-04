<?php

namespace App\Filament\Resources;

use App\Classes\Core;
use App\Enums\MessageSubmissionType;
use App\Exports\DailyAttendanceSummaryExport;
use App\Filament\Actions\SendBulkReminderToAllGroupsAction;
use App\Filament\Actions\SendMessageToAllGroupMembersAction;
use App\Filament\Resources\GroupResource\Pages;
use App\Filament\Resources\GroupResource\RelationManagers\ManagersRelationManager;
use App\Filament\Resources\GroupResource\RelationManagers\ProgressesRelationManager;
use App\Filament\Resources\GroupResource\RelationManagers\StudentsRelationManager;
use App\Models\Group;
use App\Models\GroupMessageTemplate;
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
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action as ActionsAction;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
                        Toggle::make('is_quran_group')
                            ->label('مجموعة قرآن')
                            ->default(true)
                            ->helperText('حدد إذا كانت هذه المجموعة تدرس القرآن أم لا'),
                        Forms\Components\ToggleButtons::make('message_submission_type')
                            ->label('نوع الرسائل المقبولة للتسجيل')
                            ->options(MessageSubmissionType::class)
                            ->inline()
                            ->default(MessageSubmissionType::Media),
                    ]),
                Forms\Components\TagsInput::make('ignored_names_phones')
                    ->label('أسماء وأرقام مستثناة من التسجيل')
                    ->helperText('أضف أسماء وأرقام هواتف يجب تجاهلها عند استيراد سجل الواتساب')
                    ->placeholder('أضف اسم أو رقم هاتف واضغط Enter')
                    ->separator(','),
                Forms\Components\Select::make('template_id')
                    ->label('قالب الرسائل')
                    ->options(GroupMessageTemplate::pluck('name', 'id'))
                    ->searchable()
                    ->placeholder('اختر قالب رسالة')
                    ->dehydrated(false)
                    ->afterStateUpdated(function ($state, Group $record) {
                        if ($state && $record->exists) {
                            // Sync the selected template and make it default
                                    $record->messageTemplates()->sync([
                                $state => ['is_default' => true]
                                    ]);
                        }
                    })
                    ->default(function (?Group $record) {
                        if (!$record || !$record->exists) {
                            return null;
                        }
                        return $record->messageTemplates()->wherePivot('is_default', true)->first()?->id;
                    })
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
                Tables\Columns\TextColumn::make('message_submission_type')
                    ->label('نوع الرسائل المقبولة')
                    ->badge(),
                // Tables\Columns\IconColumn::make('is_quran_group')
                //     ->label('مجموعة قرآن')
                //     ->boolean()
                //     ->trueIcon('heroicon-o-check-circle')
                //     ->falseIcon('heroicon-o-x-circle')
                //     ->trueColor('success')
                //     ->falseColor('danger'),
                Tables\Columns\TextColumn::make('messageTemplates.name')
                    ->label('قالب الرسالة')
                    ->default('لا يوجد')
                    ->badge()
                    ->color('info')
                    ->getStateUsing(function (?Group $record) {
                        if (!$record) {
                            return 'لا يوجد';
                        }
                        $defaultTemplate = $record->messageTemplates()->wherePivot('is_default', true)->first();
                        return $defaultTemplate?->name ?? 'لا يوجد';
                    }),
                Tables\Columns\TextColumn::make('managers.name')
                    ->label('المشرفون')
                    ->badge(),
                TextColumn::make('created_at')->label('تاريخ الإنشاء')
                    ->date('Y-m-d H:i:s'),
                // TextColumn::make('manager_has_set_progress')
                //     ->label('تم تسجيل الحضور؟')
                //     ->getStateUsing(function (Group $record) {
                //         $students = $record->students;
                //         $progresses = $students->map(function ($student) {
                //             return $student->progresses->where('date', now()->format('Y-m-d'))->count();
                //         });

                //         return $progresses->sum() . '/' . $students->count() . ' من الطلاب مسجلين اليوم';
                //     })
                //     ->searchable(false),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
            ->recordUrl(fn(?Group $record) => $record ? GroupResource::getUrl('edit', ['record' => $record, 'activeRelationManager' => 0]) : null)
            ->actions([

                Tables\Actions\Action::make('export_attendance')
                    ->label('تصدير كشف الحضور')
                    ->icon('heroicon-o-share')
                    ->color('success')
                    ->action(function (Group $record, ListRecords $livewire) {
                        // Get students with attendance information
                        $students = StudentsRelationManager::getSortedStudentsForAttendanceReport($record);

                        // Calculate presence percentage
                        $totalStudents = $students->count();
                        $presentStudents = $students->where('attendance_count', '>', 0)->count();
                        $presencePercentage = $totalStudents > 0 ? round(($presentStudents / $totalStudents) * 100) : 0;

                        $html = view('components.students-export-table', [
                            'showAttendanceRemark' => true,
                            'students' => $students,
                            'group' => $record,
                            'presencePercentage' => $presencePercentage,
                        ])->render();

                        // Dispatch the export event
                        $livewire->dispatch('export-table', [
                            'showAttendanceRemark' => true,
                            'html' => $html,
                            'groupName' => $record->name,
                            'presencePercentage' => $presencePercentage,
                            'dateRange' => 'آخر 30 يوم'
                        ]);
                    }),
                ActionGroup::make([
                    // Tables\Actions\Action::make('send_whatsapp_group')
                    //     ->label('أرسل رسالة للغائبين')
                    //     ->icon('heroicon-o-users')
                    //     ->color('info')
                    //     ->action(function (Group $record) {
                    //         Core::sendMessageToAbsence($record);
                    //     }),
                    // Tables\Actions\Action::make('remind_manager')
                    //     ->label('تذكير المشرفين ')
                    //     ->icon('heroicon-o-bell')
                    //     ->visible(function (Group $record) {
                    //         $one = auth()->user()->role === 'admin';
                    //         $students = $record->students;
                    //         $progresses = $students->map(function ($student) {
                    //             return $student->progresses->where('date', now()->format('Y-m-d'))->count();
                    //         });
                    //         $two = $progresses->sum() < $students->count();

                    //         return $one && $two;
                    //     })
                    //     ->color('primary')
                    //     ->form([
                    //         Textarea::make('message')
                    //             ->label('الرسالة')
                    //             ->default('من فضلكم قوموا بتسجيل الحضور للطلاب اليوم.')
                    //             ->rows(10)
                    //             ->required(),
                    //     ])
                    //     ->action(function (array $data, Group $record) {
                    //         $data['message'] = $data['message'] ?? 'من فضلكم قوموا بتسجيل الحضور للطلاب اليوم.';
                    //         $data['students'] = $record->managers()->pluck('id')->toArray();
                    //         $data['message_type'] = 'custom';
                    //         Core::sendMessageToSpecific($data, 'manager');
                    //     }),
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\RestoreAction::make(),
                    Tables\Actions\ForceDeleteAction::make(),
                ])
            ])
            ->headerActions([
                SendBulkReminderToAllGroupsAction::make(),
                SendMessageToAllGroupMembersAction::make(),

                ActionsAction::make('export_daily_attendance_summary')
                    ->label('تصدير موجز الحضور اليومي')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('success')
                    // ->visible(fn() => auth()->user()->role === 'admin')
                    ->form([
                        Forms\Components\DatePicker::make('export_date')
                            ->label('التاريخ')
                            ->default(today())
                            ->required()
                            ->helperText('اختر التاريخ المراد تصدير موجز الحضور له')
                            ->displayFormat('Y-m-d')
                            ->format('Y-m-d')
                            ->native(false)
                    ])
                    ->action(function (array $data) {
                        $selectedDate = $data['export_date'];
                        return Excel::download(new DailyAttendanceSummaryExport($selectedDate, auth()->id()), 'daily-attendance-summary-' . $selectedDate . '.xlsx');
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('toggle_quran_group')
                        ->label('تبديل حالة مجموعة القرآن')
                        ->icon('heroicon-o-academic-cap')
                        ->visible(fn() => auth()->user()->isAdministrator())
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('تبديل حالة مجموعة القرآن')
                        ->modalDescription('سيتم تبديل حالة "مجموعة قرآن" للمجموعات المحددة')
                        ->modalSubmitActionLabel('تبديل الحالة')
                        ->action(function ($records) {
                            $updatedCount = 0;

                            foreach ($records as $group) {
                                $group->update([
                                    'is_quran_group' => !$group->is_quran_group
                                ]);
                                $updatedCount++;
                            }

                            Notification::make()
                                ->title('تم تبديل حالة مجموعة القرآن')
                                ->body("تم تحديث {$updatedCount} مجموعة بنجاح")
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('set_template')
                        ->label('تعيين قالب رسالة')
                        ->icon('heroicon-o-chat-bubble-left-right')
                        ->visible(fn() => auth()->user()->isAdministrator())
                        ->color('primary')
                        ->form([
                            Forms\Components\Select::make('template_id')
                                ->label('اختر قالب الرسالة')
                                ->options(
                                    GroupMessageTemplate::pluck('name', 'id')->prepend('لا يوجد', '')
                                )
                                ->searchable()
                                ->placeholder('اختر قالب رسالة'),
                        ])
                        ->action(function (array $data, $records) {
                            $templateId = $data['template_id'] ?: null;

                            foreach ($records as $group) {
                                if ($templateId) {
                                    // Sync the selected template and make it default
                                    $group->messageTemplates()->sync([
                                        $templateId => ['is_default' => true]
                                    ]);
                                } else {
                                    // Remove all templates
                                    $group->messageTemplates()->detach();
                                }
                            }

                            $message = $templateId
                                ? 'تم تعيين قالب الرسالة للمجموعات المحددة بنجاح'
                                : 'تم إزالة قالب الرسالة من المجموعات المحددة بنجاح';

                            Notification::make()
                                ->title($message)
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\DeleteBulkAction::make()
                        ->disabled(! Core::canChange()),
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make()
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
