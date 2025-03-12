<?php

namespace App\Filament\Resources\GroupResource\RelationManagers;

use App\Classes\Core;
use App\Helpers\ProgressFormHelper;
use App\Models\Group;
use App\Models\GroupMessageTemplate;
use App\Models\Student;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup as ActionsActionGroup;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\CreateAction as ActionsCreateAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\ActionsPosition;
use Filament\Tables\Table;
use Filament\Support\Enums\ActionSize;

class StudentsRelationManager extends RelationManager
{
    protected static string $relationship = 'students';

    protected static bool $isLazy = false;

    protected static ?string $title = 'الطلاب';

    protected static ?string $navigationLabel = 'الطلب';

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $modelLabel = 'طالب';

    protected static ?string $pluralModelLabel = 'طلاب';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('الاسم')
                    ->required(),
                Forms\Components\TextInput::make('phone')
                    ->label('رقم الهاتف')
                    ->default('06')
                    ->required(),
                Forms\Components\Select::make('sex')
                    ->label('الجنس')
                    ->options([
                        'male' => 'ذكر',
                        'female' => 'أنثى',
                    ])
                    ->default('male'),
                Forms\Components\TextInput::make('city')
                    ->label('المدينة')
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('number')
                    ->label('الرقم')
                    ->state(function ($record, $rowLoop) {
                        return $rowLoop->iteration;
                    })
                    ->sortable(),
                TextColumn::make('name')
                    ->icon(function (Student $record) {
                        // Using eager loaded today_progress relationship
                        return match ($record->today_progress?->status) {
                            'memorized' => 'heroicon-o-check-circle',
                            'absent' => 'heroicon-o-exclamation-circle',
                            default => $record->today_progress ? 'heroicon-o-information-circle' : null,
                        };
                    })
                    ->searchable()
                    ->color(function (Student $record) {
                        // Using eager loaded today_progress relationship
                        return match ($record->today_progress?->status) {
                            'memorized' => 'success',
                            'absent' => 'danger',
                            default => $record->today_progress ? 'warning' : null,
                        };
                    })
                    ->label('الاسم'),
                TextColumn::make('phone')
                    ->url(fn($record) => "tel:{$record->phone}")
                    ->badge()
                    ->searchable()
                    ->icon(function (TextColumn $column, $record) {
                        $needsCall = $record->needACall;
                        $column->icon(fn($record) => $needsCall ? 'heroicon-o-exclamation-circle' : 'heroicon-o-check-circle')
                            ->color(fn(Student $record) => $needsCall ? 'danger' : 'success');
                    })
                    ->label('رقم الهاتف')
                    ->extraCellAttributes(['dir' => 'ltr'])
                    ->alignRight(),
                TextColumn::make('sex')->label('الجنس')
                    ->formatStateUsing(function ($state) {
                        return match ($state) {
                            'male' => 'ذكر',
                            'female' => 'أنثى',
                        };
                    }),
                TextColumn::make('city')->label('المدينة'),
                TextColumn::make('created_at')
                    ->label('انضم منذ')
                    ->since()
                    ->toggleable()
                    ->toggledHiddenByDefault(),
            ])

            ->reorderable('order_no', true)
            ->defaultSort('order_no')
            ->actions(
                [
                    ActionsActionGroup::make([
                        Tables\Actions\EditAction::make(),
                        Tables\Actions\DeleteAction::make(),
                        Tables\Actions\ViewAction::make(),
                    ]),
                    Tables\Actions\Action::make('send_whatsapp_msg')
                        ->color('success')
                        ->iconButton()
                        ->icon('heroicon-o-chat-bubble-oval-left')
                        ->label('إرسال رسالة واتساب')
                        ->url(function (Student $record) {
                            return self::getWhatsAppUrl($record, $this->ownerRecord);
                        }, true),

                ],
                ActionsPosition::BeforeColumns
            )
            ->paginated(false)
            ->headerActions([
                Action::make('make_others_as_absent')
                    ->label('تسجيل البقية كغائبين')
                    ->color('danger')
                    ->icon('heroicon-o-exclamation-circle')
                    ->modalSubmitActionLabel('تأكيد')
                    ->size(ActionSize::ExtraSmall)
                    ->visible(fn() => $this->ownerRecord->managers->contains(auth()->user()))
                    ->action(function () {
                        $selectedDate = $this->tableFilters['date']['value'] ?? now()->format('Y-m-d');
                        $this->ownerRecord->students->filter(function ($student) use ($selectedDate) {
                            return $student->progresses->where('date', $selectedDate)
                                ->count() == 0 || $student->progresses->where('date', $selectedDate)->where('status', null)->count();
                        })->each(function ($student) use ($selectedDate) {
                            if ($student->progresses->where('date', $selectedDate)->count() == 0) {
                                $student->progresses()->create([
                                    'date' => $selectedDate,
                                    'status' => 'absent',
                                    'comment' => null,
                                    'page_id' => null,
                                    'lines_from' => null,
                                    'lines_to' => null,
                                ]);
                            } else {
                                $student->progresses()->where('date', $selectedDate)
                                    ->update([
                                        'status' => 'absent',
                                        'comment' => null,
                                    ]);
                            }
                        });

                        Notification::make()
                            ->title('تم تسجيل الغائبين بنجاح')
                            ->success()
                            ->send();
                    }),
                ActionsActionGroup::make([
                    Action::make('copy_students_from_other_groups')
                        ->label('نسخ الطلاب من مجموعات أخرى')
                        ->icon('heroicon-o-document-duplicate')
                        ->color('primary')
                        ->visible(fn() => auth()->user()->isAdministrator())
                        ->form([
                            Forms\Components\Select::make('source_group_id')
                                ->label('المجموعة المصدر')
                                ->options(fn() => \App\Models\Group::where('id', '!=', $this->ownerRecord->id)->pluck('name', 'id'))
                                ->required()
                                ->reactive(),
                            Forms\Components\CheckboxList::make('student_ids')
                                ->label('الطلاب')
                                ->reactive()
                                ->options(function (Get $get) {
                                    $groupId = $get('source_group_id');
                                    if (! $groupId) {
                                        return [];
                                    }

                                    $currentGroupPhones = $this->ownerRecord->students()->pluck('phone');

                                    return \App\Models\Student::without(['progresses', 'group', 'progresses.page', 'group.managers'])
                                        ->where('group_id', $groupId)
                                        ->whereNotIn('phone', $currentGroupPhones)
                                        ->pluck('name', 'id');
                                })
                                ->required()
                                ->bulkToggleable(),
                        ])
                        ->action(function (array $data) {
                            $studentsToCreate = \App\Models\Student::without(['progresses', 'group', 'progresses.page', 'group.managers'])
                                ->whereIn('id', $data['student_ids'])
                                ->get();

                            $createdCount = 0;
                            foreach ($studentsToCreate as $student) {
                                if (! $this->ownerRecord->students()->where('phone', $student->phone)->exists()) {
                                    $newStudentData = $student->only([
                                        'name',
                                        'phone',
                                        'sex',
                                        'city',
                                        // Add any other fields you want to copy here
                                    ]);

                                    $this->ownerRecord->students()->create($newStudentData);
                                    $createdCount++;
                                }
                            }

                            Notification::make()
                                ->title("تم نسخ {$createdCount} طالب بنجاح")
                                ->success()
                                ->send();
                        }),
                    ActionsCreateAction::make()
                        ->label('إضافة طالب')
                        ->icon('heroicon-o-plus-circle')
                        ->visible(fn() => $this->ownerRecord->managers->contains(auth()->user()))
                        ->slideOver(),

                    Action::make('manage_message_templates')
                        ->label('إدارة قوالب الرسائل')
                        ->icon('heroicon-o-chat-bubble-left-right')
                        ->color('primary')
                        ->size(ActionSize::Small)
                        ->visible(fn() => $this->ownerRecord->managers->contains(auth()->user()))
                        ->form([
                            Tabs::make('Message Templates')
                                ->tabs([
                                    Tabs\Tab::make('إضافة قالب جديد')
                                        ->icon('heroicon-o-plus-circle')
                                        ->schema([
                                            Forms\Components\TextInput::make('name')
                                                ->label('اسم القالب')
                                                ->required(),
                                            Textarea::make('content')
                                                ->label('محتوى الرسالة')
                                                ->required()
                                                ->helperText('يمكنك استخدام المتغيرات التالية: {{student_name}}, {{group_name}}, {{curr_date}}, {{prefix}}, {{pronoun}}, {{verb}}')
                                                ->columnSpanFull(),
                                            Toggle::make('is_default')
                                                ->label('قالب افتراضي')
                                                ->helperText('إذا تم تحديد هذا الخيار، سيتم استخدام هذا القالب كقالب افتراضي للمجموعة')
                                                ->default(false),
                                        ]),
                                    Tabs\Tab::make('قوالب الرسائل الحالية')
                                        ->icon('heroicon-o-clipboard-document-list')
                                        ->schema([
                                            Forms\Components\Repeater::make('templates')
                                                ->label('')
                                                ->schema([
                                                    Forms\Components\TextInput::make('name')
                                                        ->label('اسم القالب')
                                                        ->required(),
                                                    Textarea::make('content')
                                                        ->label('محتوى الرسالة')
                                                        ->required()
                                                        ->helperText('يمكنك استخدام المتغيرات التالية: {{student_name}}, {{group_name}}, {{curr_date}}, {{prefix}}, {{pronoun}}, {{verb}}')
                                                        ->columnSpanFull(),
                                                    Toggle::make('is_default')
                                                        ->label('قالب افتراضي')
                                                        ->helperText('إذا تم تحديد هذا الخيار، سيتم استخدام هذا القالب كقالب افتراضي للمجموعة')
                                                        ->default(false),
                                                    Forms\Components\Hidden::make('id'),
                                                ])
                                                ->itemLabel(fn(array $state): ?string => $state['name'] ?? null)
                                                ->collapsible()
                                                ->defaultItems(0)
                                                ->reorderable(false)
                                                ->addable(false)
                                                ->deletable(true)
                                                ->deleteAction(
                                                    fn(Forms\Components\Actions\Action $action) => $action->requiresConfirmation()
                                                )
                                        ]),
                                ])
                                ->activeTab(0)
                        ])
                        ->action(function (array $data) {
                            // Handle new template creation
                            if (!empty($data['name']) && !empty($data['content'])) {
                                // If this is set as default, unset any existing defaults
                                if (!empty($data['is_default']) && $data['is_default']) {
                                    $this->ownerRecord->messageTemplates()->update(['is_default' => false]);
                                }

                                // Create the new template
                                $this->ownerRecord->messageTemplates()->create([
                                    'name' => $data['name'],
                                    'content' => $data['content'],
                                    'is_default' => !empty($data['is_default']) ? $data['is_default'] : false,
                                ]);

                                Notification::make()
                                    ->title('تم إضافة قالب الرسالة بنجاح')
                                    ->success()
                                    ->send();
                            }

                            // Handle existing templates updates
                            if (!empty($data['templates'])) {
                                foreach ($data['templates'] as $templateData) {
                                    if (!empty($templateData['id'])) {
                                        $template = GroupMessageTemplate::find($templateData['id']);

                                        if ($template) {
                                            // If this is set as default, unset any existing defaults
                                            if (!empty($templateData['is_default']) && $templateData['is_default']) {
                                                $this->ownerRecord->messageTemplates()
                                                    ->where('id', '!=', $template->id)
                                                    ->update(['is_default' => false]);
                                            }

                                            $template->update([
                                                'name' => $templateData['name'],
                                                'content' => $templateData['content'],
                                                'is_default' => !empty($templateData['is_default']) ? $templateData['is_default'] : false,
                                            ]);
                                        }
                                    }
                                }

                                Notification::make()
                                    ->title('تم تحديث قوالب الرسائل بنجاح')
                                    ->success()
                                    ->send();
                            }
                        })
                        ->mutateFormDataUsing(function (array $data) {
                            // Load existing templates
                            $templates = $this->ownerRecord->messageTemplates()->get();
                            $data['templates'] = $templates->map(function ($template) {
                                return [
                                    'id' => $template->id,
                                    'name' => $template->name,
                                    'content' => $template->content,
                                    'is_default' => $template->is_default,
                                ];
                            })->toArray();

                            return $data;
                        }),
                    Action::make('send_msg_to_others')
                        ->label('إرسال رسالة تذكير للبقية')
                        ->icon('heroicon-o-chat-bubble-oval-left')
                        ->color('warning')
                        ->form([
                            Forms\Components\Select::make('template_id')
                                ->label('اختر قالب الرسالة')
                                ->options(function () {
                                    return $this->ownerRecord->messageTemplates()->pluck('name', 'id')
                                        ->prepend('رسالة مخصصة', 'custom');
                                })
                                ->default('custom')
                                ->reactive(),
                            Textarea::make('message')
                                ->hint('يمكنك استخدام المتغيرات التالية: {{student_name}}, {{group_name}}, {{curr_date}}, {{prefix}}, {{pronoun}}, {{verb}}')
                                ->default('لم ترسلوا الواجب المقرر اليوم، لعل المانع خير.')
                                ->label('الرسالة')
                                ->required()
                                ->hidden(fn(Get $get) => $get('template_id') !== 'custom'),
                        ])
                        ->visible(fn() => $this->ownerRecord->managers->contains(auth()->user()))
                        ->action(function (array $data) {
                            $selectedDate = $this->tableFilters['date']['value'] ?? now()->format('Y-m-d');

                            // Get the message content
                            $messageTemplate = '';
                            if ($data['template_id'] === 'custom') {
                                $messageTemplate = $data['message'];
                            } else {
                                $template = GroupMessageTemplate::find($data['template_id']);
                                if ($template) {
                                    $messageTemplate = $template->content;
                                } else {
                                    $messageTemplate = $data['message'] ?? 'لم ترسلوا الواجب المقرر اليوم، لعل المانع خير.';
                                }
                            }

                            $this->ownerRecord->students->filter(function ($student) use ($selectedDate) {
                                return $student->progresses->where('date', $selectedDate)->count() == 0;
                            })->each(function ($student) use ($selectedDate, $messageTemplate) {
                                $student->progresses()->create([
                                    'date' => $selectedDate,
                                    'status' => 'absent',
                                    'page_id' => null,
                                    'ayah_id' => null,
                                    'note' => 'تم تسجيل الغياب تلقائيا',
                                ]);
                                if ($selectedDate == now()->format('Y-m-d')) {
                                    $processedMessage = Core::processMessageTemplate($messageTemplate, $student, $this->ownerRecord);
                                    Core::sendSpecifMessageToStudent($student, $processedMessage);
                                }
                            });
                        }),
                    Action::make('send_msg_to_absent')
                        ->label('إرسال رسالة تذكير للغائبين')
                        ->icon('heroicon-o-chat-bubble-oval-left')
                        ->color('danger')
                        ->form([
                            Forms\Components\Select::make('template_id')
                                ->label('اختر قالب الرسالة')
                                ->options(function () {
                                    return $this->ownerRecord->messageTemplates()->pluck('name', 'id')
                                        ->prepend('رسالة مخصصة', 'custom');
                                })
                                ->default('custom')
                                ->reactive(),
                            Textarea::make('message')
                                ->hint('يمكنك استخدام المتغيرات التالية: {{student_name}}, {{group_name}}, {{curr_date}}, {{prefix}}, {{pronoun}}, {{verb}}')
                                ->default('لم ترسلوا الواجب المقرر اليوم، لعل المانع خير.')
                                ->label('الرسالة')
                                ->required()
                                ->hidden(fn(Get $get) => $get('template_id') !== 'custom'),
                        ])
                        ->visible(fn() => $this->ownerRecord->managers->contains(auth()->user()))
                        ->action(function (array $data) {
                            $selectedDate = $this->tableFilters['date']['value'] ?? now()->format('Y-m-d');

                            // Get the message content
                            $messageTemplate = '';
                            if ($data['template_id'] === 'custom') {
                                $messageTemplate = $data['message'];
                            } else {
                                $template = GroupMessageTemplate::find($data['template_id']);
                                if ($template) {
                                    $messageTemplate = $template->content;
                                } else {
                                    $messageTemplate = $data['message'] ?? 'لم ترسلوا الواجب المقرر اليوم، لعل المانع خير.';
                                }
                            }

                            $this->ownerRecord->students->filter(function ($student) use ($selectedDate) {
                                return $student->progresses->where('date', $selectedDate)->where('status', 'absent')->count() > 0;
                            })->each(function ($student) use ($selectedDate, $messageTemplate) {
                                if ($selectedDate == now()->format('Y-m-d')) {
                                    $processedMessage = Core::processMessageTemplate($messageTemplate, $student, $this->ownerRecord);
                                    Core::sendSpecifMessageToStudent($student, $processedMessage);
                                }
                            });
                        }),
                ]),
                Action::make('export_table')
                    ->label('إرسال التقرير')
                    ->icon('heroicon-o-share')
                    ->size(ActionSize::Small)
                    ->color('success')
                    ->action(function () {

                        $students = $this->ownerRecord->students()
                            ->withCount(['progresses as attendance_count' => function ($query) {
                                $query->where('date', now()->subMinutes(30)->format('Y-m-d'))->where('status', 'memorized');
                            }])
                            ->orderByDesc('attendance_count')
                            ->get();

                        $html = view('components.students-export-table', [
                            'students' => $students,
                            'group' => $this->ownerRecord,
                        ])->render();

                        $this->dispatch('export-table', [
                            'html' => $html,
                            'groupName' => $this->ownerRecord->name
                        ]);
                    })

            ])
            ->paginated(true)
            ->defaultPaginationPageOption(4)
            ->bulkActions([
                BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),

                    BulkAction::make('send_msg')
                        ->label('إرسال رسالة ')
                        ->icon('heroicon-o-chat-bubble-oval-left')
                        ->color('warning')
                        ->form([
                            Forms\Components\Select::make('template_id')
                                ->label('اختر قالب الرسالة')
                                ->options(function () {
                                    return $this->ownerRecord->messageTemplates()->pluck('name', 'id')
                                        ->prepend('رسالة مخصصة', 'custom');
                                })
                                ->default('custom')
                                ->reactive(),
                            Textarea::make('message')
                                ->hint('يمكنك استخدام المتغيرات التالية: {{student_name}}, {{group_name}}, {{curr_date}}, {{prefix}}, {{pronoun}}, {{verb}}')
                                ->default('لم ترسل الواجب المقرر اليوم، لعل المانع خير.')
                                ->label('الرسالة')
                                ->required()
                                ->hidden(fn(Get $get) => $get('template_id') !== 'custom'),
                        ])
                        ->visible(fn() => $this->ownerRecord->managers->contains(auth()->user()))
                        ->action(function (array $data) {
                            $students = $this->selectedTableRecords;

                            // Get the message content
                            $messageTemplate = '';
                            if ($data['template_id'] === 'custom') {
                                $messageTemplate = $data['message'];
                            } else {
                                $template = GroupMessageTemplate::find($data['template_id']);
                                if ($template) {
                                    $messageTemplate = $template->content;
                                } else {
                                    $messageTemplate = $data['message'] ?? 'لم ترسل الواجب المقرر اليوم، لعل المانع خير.';
                                }
                            }

                            foreach ($students as $studentId) {
                                $student = Student::find($studentId);
                                $processedMessage = Core::processMessageTemplate($messageTemplate, $student, $this->ownerRecord);
                                Core::sendSpecifMessageToStudent($student, $processedMessage);
                            }
                        })->deselectRecordsAfterCompletion(),
                ]),
                BulkAction::make('set_as_absent')
                    ->label('غائبين')
                    ->color('danger')
                    ->icon('heroicon-o-exclamation-circle')
                    ->requiresConfirmation()
                    ->modalSubmitActionLabel('تأكيد')
                    ->form([
                        Toggle::make('send_msg')
                            ->label('تأكيد إرسال رسالة تذكير')
                            ->reactive()
                            ->default(false),
                        Textarea::make('message')
                            ->hint('السلام عليكم وإسم الطالب سيتم إضافته تلقائياً في  الرسالة.')
                            ->reactive()
                            ->hidden(fn(Get $get) => ! $get('send_msg'))
                            ->default('لم ترسلوا الواجب المقرر اليوم، لعل المانع خير.')
                            ->label('الرسالة')
                            ->required(),
                    ])
                    ->visible(fn() => $this->ownerRecord->managers->contains(auth()->user()))
                    ->action(function (array $data) {
                        $students = $this->selectedTableRecords;
                        foreach ($students as $studentId) {
                            $student = Student::find($studentId);
                            $selectedDate = now()->format('Y-m-d');
                            if ($student->progresses->where('date', $selectedDate)->count() == 0) {
                                $student->progresses()->create([
                                    'date' => $selectedDate,
                                    'status' => 'absent',
                                    'comment' => $data['send_msg'] ? 'message_sent' : null,
                                    'page_id' => null,
                                    'lines_from' => null,
                                    'lines_to' => null,
                                ]);
                            } else {
                                $student->progresses()->where('date', $selectedDate)->update([
                                    'status' => 'absent',
                                    'comment' => 'message_sent',
                                ]);
                            }
                            if ($data['send_msg']) {
                                $msg = $data['message'];
                                Core::sendSpecifMessageToStudent($student, $msg);
                            }
                        }
                    })->deselectRecordsAfterCompletion(),
                Tables\Actions\BulkAction::make('set_prgress')
                    ->label('حاضرين')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn() => $this->ownerRecord->managers->contains(auth()->user()))
                    ->deselectRecordsAfterCompletion()
                    ->action(function () {
                        $students = $this->selectedTableRecords;
                        foreach ($students as $studentId) {
                            $student = Student::find($studentId);
                            // $data = ProgressFormHelper::calculateNextProgress($student);
                            if ($student->progresses->where('date', now()->format('Y-m-d'))->count() == 0) {
                                $student->progresses()->create([
                                    'created_by' => auth()->id(),
                                    'date' => now()->format('Y-m-d'),
                                    'page_id' => null,
                                    'lines_from' => null,
                                    'lines_to' => null,
                                    'status' => 'memorized',
                                ]);
                            } else {
                                $progress = $student->progresses->where('date', now()->format('Y-m-d'))->first();
                                $progress->update([
                                    'page_id' => null,
                                    'lines_from' => null,
                                    'lines_to' => null,
                                    'status' => 'memorized',
                                ]);
                            }
                        }
                    }),
            ])
            ->query(function () {
                $today = now()->format('Y-m-d');

                return $this->ownerRecord->students()
                    ->withCount([
                        'progresses as attendance_count' => function ($query) use ($today) {
                            $query->where('date', $today)
                                ->where('status', 'memorized');
                        },
                        'progresses as needs_call' => function ($query) {
                            $query->where('status', 'absent')
                                ->latest()
                                ->limit(3);
                        }
                    ])
                    ->with([
                        'today_progress' => function ($query) use ($today) {
                            $query->where('date', $today)
                                ->latest();
                        }
                    ])
                    ->orderByDesc('attendance_count');
            });
    }

    public function isReadOnly(): bool
    {
        return ! $this->ownerRecord->managers->contains(auth()->user());
    }

    public static function getWhatsAppUrl(Student $record, Group $ownerRecord): string
    {
        // Format phone number for WhatsApp
        $number = $record->phone;

        // Remove any spaces, dashes or special characters
        $number = preg_replace('/[^0-9]/', '', $number);

        // Handle different Moroccan number formats
        if (strlen($number) === 9 && in_array(substr($number, 0, 1), ['6', '7'])) {
            // If number starts with 6 or 7 and is 9 digits
            $number = '+212' . $number;
        } elseif (strlen($number) === 10 && in_array(substr($number, 0, 2), ['06', '07'])) {
            // If number starts with 06 or 07 and is 10 digits
            $number = '+212' . substr($number, 1);
        } elseif (strlen($number) === 12 && substr($number, 0, 3) === '212') {
            // If number already has 212 country code
            $number = '+' . $number;
        }

        // Get gender-specific terms for fallback templates
        $genderTerms = $record->sex === 'female' ? [
            'prefix' => 'أختي الطالبة',
            'pronoun' => 'ك',
            'verb' => 'تنسي'
        ] : [
            'prefix' => 'أخي الطالب',
            'pronoun' => 'ك',
            'verb' => 'تنس'
        ];
        $name = trim($record->name);

        // Check if the group has a selected message template
        if ($ownerRecord->message_id && $ownerRecord->message) {
            $message = Core::processMessageTemplate($ownerRecord->message->content, $record, $ownerRecord);
        }
        // Check if there's a default template in the group's message templates
        else if ($defaultTemplate = $ownerRecord->messageTemplates()->where('is_default', true)->first()) {
            $message = Core::processMessageTemplate($defaultTemplate->content, $record, $ownerRecord);
        }
        // Use built-in templates based on group type
        else {
            // Message for onsite groups
            if ($ownerRecord->is_onsite) {
                $message = <<<MSG
السلام عليكم ورحمة الله وبركاته
{$genderTerms['prefix']} {$name}،
لقد تم تسجيل غيابكم عن حصة القرآن الحضورية، نرجوا أن يكون المانع خيرا، كما ونحثّكم على أن تحرصوا على الحضور الحصة المقبلة إن شاء الله. زادكم الله حرصا
MSG;
            } else {
                // Default message template
                $message = <<<MSG
السلام عليكم ورحمة الله وبركاته
*{$genderTerms['prefix']} {$name}*،
نذكر{$genderTerms['pronoun']} بالواجب المقرر اليوم، لعل المانع خير. 🌟
MSG;

                // Customize message based on group type
                if (str_contains($ownerRecord->type, 'سرد')) {
                    $message = <<<MSG
السلام عليكم ورحمة الله وبركاته
*{$genderTerms['prefix']} {$name}*،
نذكر{$genderTerms['pronoun']} بواجب اليوم من السرد ✨
المرجو المبادرة قبل غلق المجموعة
_زاد{$genderTerms['pronoun']} الله حرصا_ 🌙
MSG;
                } elseif (str_contains($ownerRecord->type, 'ثبيت') || str_contains($ownerRecord->name, 'تَّثبيت')) {
                    $message = <<<MSG
                السلام عليكم ورحمة الله وبركاته
                *{$genderTerms['prefix']} {$name}*
                لا {$genderTerms['verb']} الاستظهار في مجموعة التثبيت ✨
                _بارك الله في{$genderTerms['pronoun']} وزاد{$genderTerms['pronoun']} حرصا_ 🌟
                MSG;
                } elseif (str_contains($ownerRecord->type, 'مراجعة') || str_contains($ownerRecord->name, 'مراجعة')) {
                    $message = <<<MSG
السلام عليكم ورحمة الله وبركاته
*{$genderTerms['prefix']} {$name}*
لا {$genderTerms['verb']} الاستظهار في مجموعة المراجعة ✨
_بارك الله في{$genderTerms['pronoun']} وزاد{$genderTerms['pronoun']} حرصا_ 🌟
MSG;
                } elseif (str_contains($ownerRecord->type, 'عتصام') || str_contains($ownerRecord->name, 'عتصام')) {
                    $message = <<<MSG
السلام عليكم ورحمة الله وبركاته
*{$genderTerms['prefix']} {$name}*
لا {$genderTerms['verb']} استظهار واجب الاعتصام
_بارك الله في{$genderTerms['pronoun']} وزاد{$genderTerms['pronoun']} حرصا_ 🌟
MSG;
                }
            }
        }

        $url = route('whatsapp', ['number' => $number, 'message' => $message, 'student_id' => $record->id]);
        // Open in new tab
        return $url;
    }
}
