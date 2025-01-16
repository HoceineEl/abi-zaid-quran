<?php

namespace App\Filament\Resources\GroupResource\RelationManagers;

use App\Classes\Core;
use App\Helpers\ProgressFormHelper;
use App\Models\Group;
use App\Models\Student;
use Filament\Forms;
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
                    Action::make('make_others_as_absent')
                        ->label('تسجيل البقية كغائبين')
                        ->color('danger')
                        ->icon('heroicon-o-exclamation-circle')
                        ->form([
                            Toggle::make('send_msg')
                                ->label('أكيد إرسال رسالة تذكير')
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
                        ->modalSubmitActionLabel('تأكيد')
                        ->visible(fn() => $this->ownerRecord->managers->contains(auth()->user()))
                        ->action(function (array $data) {
                            $selectedDate = $this->tableFilters['date']['value'] ?? now()->format('Y-m-d');
                            $this->ownerRecord->students->filter(function ($student) use ($selectedDate) {
                                return $student->progresses->where('date', $selectedDate)
                                    ->count() == 0 || $student->progresses->where('date', $selectedDate)->where('status', null)->count();
                            })->each(function ($student) use ($selectedDate, $data) {
                                if ($student->progresses->where('date', $selectedDate)->count() == 0) {
                                    $student->progresses()->create([
                                        'date' => $selectedDate,
                                        'status' => 'absent',
                                        'comment' => 'message_sent',
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
                            });
                        }),
                    Action::make('send_msg_to_others')
                        ->label('إرسال رسالة تذكير للبقية')
                        ->icon('heroicon-o-chat-bubble-oval-left')
                        ->color('warning')
                        ->form([
                            Textarea::make('message')
                                ->hint('السلام عليكم وإسم الطالب سيتم إضافته تلقائياً في  الرسالة.')
                                ->default('لم ترسلوا الواجب المقرر اليوم، لعل المانع خير.')
                                ->label('الرسالة')
                                ->required(),
                        ])
                        ->visible(fn() => $this->ownerRecord->managers->contains(auth()->user()))
                        ->action(function (array $data) {
                            $selectedDate = $this->tableFilters['date']['value'] ?? now()->format('Y-m-d');
                            $this->ownerRecord->students->filter(function ($student) use ($selectedDate) {
                                return $student->progresses->where('date', $selectedDate)->count() == 0;
                            })->each(function ($student) use ($selectedDate, $data) {
                                $student->progresses()->create([
                                    'date' => $selectedDate,
                                    'status' => null,
                                    'comment' => 'message_sent',
                                    'page_id' => null,
                                    'lines_from' => null,
                                    'lines_to' => null,
                                ]);
                                if ($selectedDate == now()->format('Y-m-d')) {
                                    $msg = $data['message'];
                                    Core::sendSpecifMessageToStudent($student, $msg);
                                }
                            });
                        }),
                    Action::make('send_msg_to_absent')
                        ->label('إرسال رسالة تذكير للغائبين')
                        ->icon('heroicon-o-chat-bubble-oval-left')
                        ->color('danger')
                        ->form([
                            Textarea::make('message')
                                ->hint('السلام عليكم وإسم الطالب سيتم إضافته تلقائياً في  الرسالة.')
                                ->default('لم ترسلوا الواجب المقرر اليوم، لعل المانع خير.')
                                ->label('الرسالة')
                                ->required(),
                        ])
                        ->visible(fn() => $this->ownerRecord->managers->contains(auth()->user()))
                        ->action(function (array $data) {
                            $selectedDate = $this->tableFilters['date']['value'] ?? now()->format('Y-m-d');
                            $this->ownerRecord->students->filter(function ($student) use ($selectedDate) {
                                return $student->progresses->where('date', $selectedDate)->where('status', 'absent')->count() > 0;
                            })->each(function ($student) use ($selectedDate, $data) {
                                if ($selectedDate == now()->format('Y-m-d')) {
                                    $msg = $data['message'];
                                    Core::sendSpecifMessageToStudent($student, $msg);
                                }
                            });
                        }),
                ]),
                Action::make('export_table')
                    ->label('تصدير كصورة')
                    ->icon('heroicon-o-share')
                    ->size(ActionSize::Small)
                    ->color('success')
                    ->action(function () {
                        $selectedDate = $this->tableFilters['date']['value'] ?? now()->format('Y-m-d');

                        $students = $this->ownerRecord->students()
                            ->withCount(['progresses as attendance_count' => function ($query) {
                                $query->where('date', now()->format('Y-m-d'))->where('status', 'memorized');
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
            ->bulkActions([
                BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),

                    BulkAction::make('send_msg')
                        ->label('إرسال رسالة ')
                        ->icon('heroicon-o-chat-bubble-oval-left')
                        ->color('warning')
                        ->form([
                            Textarea::make('message')
                                ->hint('السلام عليكم وإسم الطالب سيتم إضافته تلقائياً في  الرسالة.')
                                ->default('لم ترسل الواجب المقرر اليوم، لعل المانع خير.')
                                ->label('الرسالة')
                                ->required(),
                        ])
                        // ->visible(fn() => $this->ownerRecord->managers->contains(auth()->user()))
                        ->hidden()
                        ->action(function (array $data) {
                            $students = $this->selectedTableRecords;
                            foreach ($students as $studentId) {
                                $student = Student::find($studentId);
                                $msg = $data['message'];
                                Core::sendSpecifMessageToStudent($student, $msg);
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
                    ->requiresConfirmation()
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

        // Get gender-specific terms
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

        $url = route('whatsapp', ['number' => $number, 'message' => $message, 'student_id' => $record->id]);
        // Open in new tab
        return $url;
    }
}
