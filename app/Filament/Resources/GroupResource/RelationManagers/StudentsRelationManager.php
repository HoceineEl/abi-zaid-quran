<?php

namespace App\Filament\Resources\GroupResource\RelationManagers;

use App\Classes\Core;
use App\Enums\MessageSubmissionType;
use App\Helpers\ProgressFormHelper;
use App\Models\Group;
use App\Models\GroupMessageTemplate;
use App\Models\Progress;
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
use Propaganistas\LaravelPhone\PhoneNumber;

class StudentsRelationManager extends RelationManager
{
    protected static string $relationship = 'students';

    protected static bool $isLazy = false;

    protected static ?string $title = 'الطلاب';

    protected static ?string $navigationLabel = 'الطب';

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
                    ->form([
                        Toggle::make('with_reason')
                            ->label('غياب بعذر')
                            ->reactive()
                            ->default(false),
                    ])
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
                                    'with_reason' => $data['with_reason'] ?? false,
                                    'comment' => null,
                                    'page_id' => null,
                                    'lines_from' => null,
                                    'lines_to' => null,
                                ]);
                            } else {
                                $student->progresses()->where('date', $selectedDate)
                                    ->update([
                                        'status' => 'absent',
                                        'with_reason' => $data['with_reason'] ?? false,
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
                                // Create the new template
                                $template = GroupMessageTemplate::create([
                                    'name' => $data['name'],
                                    'content' => $data['content'],
                                ]);

                                // Attach the template to the group
                                $this->ownerRecord->messageTemplates()->attach($template->id, [
                                    'is_default' => !empty($data['is_default']) ? $data['is_default'] : false,
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ]);

                                // If this is set as default, unset any existing defaults
                                if (!empty($data['is_default']) && $data['is_default']) {
                                    $this->ownerRecord->messageTemplates()
                                        ->wherePivot('group_message_template_id', '!=', $template->id)
                                        ->updateExistingPivot($template->id, ['is_default' => false]);
                                }

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
                                            // Update the template
                                            $template->update([
                                                'name' => $templateData['name'],
                                                'content' => $templateData['content'],
                                            ]);

                                            // Update the pivot data
                                            $this->ownerRecord->messageTemplates()->updateExistingPivot($template->id, [
                                                'is_default' => !empty($templateData['is_default']) ? $templateData['is_default'] : false,
                                                'updated_at' => now(),
                                            ]);

                                            // If this is set as default, unset any existing defaults
                                            if (!empty($templateData['is_default']) && $templateData['is_default']) {
                                                $this->ownerRecord->messageTemplates()
                                                    ->wherePivot('group_message_template_id', '!=', $template->id)
                                                    ->updateExistingPivot($template->id, ['is_default' => false]);
                                            }
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
                                    'is_default' => $template->pivot->is_default,
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
                            Toggle::make('with_reason')
                                ->label('غياب بعذر')
                                ->reactive()
                                ->default(false),
                            Textarea::make('message')
                                ->hint('يمكنك استخدام المتغيرات التالية: {{student_name}}, {{group_name}}, {{curr_date}}, {{prefix}}, {{pronoun}}, {{verb}}')
                                ->default('لم ترسل الواجب المقرر اليوم، لعل المانع خير.')
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
                                    $messageTemplate = $data['message'] ?? 'لم ترسل الواجب المقرر اليوم، لعل المانع خير.';
                                }
                            }

                            $this->ownerRecord->students->filter(function ($student) use ($selectedDate) {
                                return $student->progresses->where('date', $selectedDate)->count() == 0;
                            })->each(function ($student) use ($selectedDate, $messageTemplate, $data) {
                                $student->progresses()->create([
                                    'date' => $selectedDate,
                                    'status' => 'absent',
                                    'with_reason' => $data['with_reason'] ?? false,
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
                                    $messageTemplate = $data['message'] ?? 'لم ترسل الواجب المقرر اليوم، لعل المانع خير.';
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
                    ->label('تصدير كشف الحضور')
                    ->icon('heroicon-o-share')
                    ->size(ActionSize::Small)
                    ->color('success')
                    ->action(function () {
                        $students = self::getSortedStudentsForAttendanceReport($this->ownerRecord);

                        // Calculate presence percentage
                        $totalStudents = $students->count();
                        $presentStudents = $students->where('attendance_count', '>', 0)->count();
                        $presencePercentage = $totalStudents > 0 ? round(($presentStudents / $totalStudents) * 100) : 0;

                        $html = view('components.students-export-table', [
                            'showAttendanceRemark' => true,
                            'students' => $students,
                            'group' => $this->ownerRecord,
                            'presencePercentage' => $presencePercentage,
                        ])->render();

                        $this->dispatch('export-table', [
                            'showAttendanceRemark' => true,
                            'html' => $html,
                            'groupName' => $this->ownerRecord->name,
                            'presencePercentage' => $presencePercentage,
                            'dateRange' => 'آخر 30 يوم'
                        ]);
                    }),
                Action::make('import_whatsapp_attendance')
                    ->label('التحضير بسجل الواتساب')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->size(ActionSize::Small)
                    ->color('primary')
                    ->visible(fn() => $this->ownerRecord->managers->contains(auth()->user()))
                    ->form([
                        Forms\Components\FileUpload::make('chat_file')
                            ->label('ملف محادثة واتساب')
                            ->disk('local')
                            ->directory('uploads')
                            ->required(),
                        Forms\Components\Toggle::make('register_rest_absent')
                            ->label('تسجيل البقية كغائبين اليوم')
                            ->default(false),
                    ])
                    ->action(function (array $data) {
                        $uploadedPath = $data['chat_file'];
                        $storagePath = storage_path('app/' . $uploadedPath);

                        if (!file_exists($storagePath)) {
                            Notification::make()
                                ->warning()
                                ->title('لم يتم العثور على الملف المرفوع.')
                                ->send();
                            return;
                        }

                        $isZip = strtolower(pathinfo($storagePath, PATHINFO_EXTENSION)) === 'zip';
                        $txtPath = $storagePath;
                        $tempTxtPath = null;

                        if ($isZip) {
                            $zip = new \ZipArchive();
                            if ($zip->open($storagePath) === true) {
                                for ($i = 0; $i < $zip->numFiles; $i++) {
                                    $entry = $zip->getNameIndex($i);
                                    if (strtolower(pathinfo($entry, PATHINFO_EXTENSION)) === 'txt') {
                                        $tempTxtPath = storage_path('app/tmp_' . uniqid() . '.txt');
                                        copy('zip://' . $storagePath . '#' . $entry, $tempTxtPath);
                                        $txtPath = $tempTxtPath;
                                        break;
                                    }
                                }
                                $zip->close();
                            }
                        }

                        $fileContent = file_get_contents($txtPath);
                        $lines = explode("\n", $fileContent);

                        // Parse WhatsApp chat lines
                        $parsedMessages = collect($lines)
                            ->filter()
                            ->map(function ($line) {
                                // English format: "01/05/2025, 05:57 - Abdullah Belguerbi: Message"
                                // French format: "29/04/2025, 05:59 - +212 677-523384: *السلام عليكم" // Uses ',' like English
                                // Arabic format: "1‏/5‏/2025، 09:17 - ‏‪+212 616-465609‬‏: <تم استبعاد الوسائط>" // Uses '،'

                                // Try English/French pattern first (uses ',') - Allows D/M/YY or D/M/YYYY
                                if (preg_match('/^(\d{1,2}\/\d{1,2}\/\d{2,4}),\s*(\d{1,2}:\d{2})\s*-\s*([^:]+):\s*(.*)$/u', $line, $matches)) {
                                    return [
                                        'date' => trim($matches[1]), // e.g., "01/05/2025"
                                        'time' => trim($matches[2]), // e.g., "05:57"
                                        'author' => trim($matches[3]), // e.g., "Abdullah Belguerbi" or "+212 677-523384"
                                        'text' => trim($matches[4]), // e.g., "Message"
                                        'format' => 'english/french', // Combined format flag
                                    ];
                                }
                                // Try Arabic pattern (uses '،') - Allows D/M/Y with flexible separators /, ., or RTL mark
                                elseif (preg_match('/^([\d\/\.\x{200F}]+)،\s*(\d{1,2}:\d{2})\s*-\s*([^:]+):\s*(.*)$/u', $line, $matches)) {
                                    // Clean date: remove potential RTL marks for consistent parsing later
                                    $cleanedDate = preg_replace('/[\x{200E}\x{200F}]/u', '', $matches[1]);
                                    return [
                                        'date' => trim($cleanedDate), // e.g., "1/5/2025"
                                        'time' => trim($matches[2]), // e.g., "09:17"
                                        'author' => trim($matches[3]), // e.g., "‏‪+212 616-465609‬‏"
                                        'text' => trim($matches[4]), // e.g., "<تم استبعاد الوسائط>"
                                        'format' => 'arabic',
                                    ];
                                }
                                return null; // Line doesn't match known formats or is a continuation line
                            })
                            ->filter(); // Remove nulls from non-matching lines

                        if ($parsedMessages->isEmpty()) {
                            if ($tempTxtPath && file_exists($tempTxtPath)) {
                                unlink($tempTxtPath);
                            }
                            Notification::make()
                                ->warning()
                                ->title('لم يتم العثور على رسائل قابلة للتحليل.')
                                ->send();
                            return;
                        }

                        // Get the latest date in the chat and determine processing date
                        $latestChatDate = null;
                        $todayDate = \Carbon\Carbon::now()->startOfDay();
                        $processingDate = $todayDate; // Default to today
                        $processingDateStr = null;

                        try {
                            // Find the latest valid date from messages
                            $parsedDates = $parsedMessages->map(function ($msg) {
                                $dateStr = $msg['date'];
                                $format = $msg['format'] ?? 'arabic';

                                // Parse date based on format
                                if ($format === 'english') {
                                    // Handle US date format (M/D/Y) directly
                                    if (preg_match('/(\d{1,2})\/(\d{1,2})\/(\d{2,4})/', $dateStr, $dateParts)) {
                                        $month = (int)$dateParts[1];
                                        $day = (int)$dateParts[2];
                                        $year = (int)$dateParts[3];

                                        if ($year < 100) $year += 2000;

                                        try {
                                            return [
                                                'message' => $msg,
                                                'datetime' => \Carbon\Carbon::createFromDate($year, $month, $day)->startOfDay()
                                            ];
                                        } catch (\Exception $e) {
                                            return null;
                                        }
                                    }
                                } else {
                                    // Handle Arabic/French format (D/M/Y)
                                    if (preg_match('/(\d{1,2})[\/\.](\d{1,2})[\/\.](\d{2,4})/', $dateStr, $dateParts)) {
                                        $day = (int)$dateParts[1];
                                        $month = (int)$dateParts[2];
                                        $year = (int)$dateParts[3];

                                        // Check if it's a valid date
                                        if ($month > 12) {
                                            // This might be M/D/Y format (US style)
                                            $temp = $day;
                                            $day = $month;
                                            $month = $temp;
                                        }

                                        if ($year < 100) $year += 2000;

                                        try {
                                            return [
                                                'message' => $msg,
                                                'datetime' => \Carbon\Carbon::createFromDate($year, $month, $day)->startOfDay()
                                            ];
                                        } catch (\Exception $e) {
                                            return null;
                                        }
                                    }
                                }

                                return null;
                            })
                                ->filter()
                                ->sortByDesc(function ($item) {
                                    return $item['datetime'];
                                });

                            if ($parsedDates->isNotEmpty()) {
                                $latestChatDate = $parsedDates->first()['datetime'];
                                // If the latest date in chat is not today, use it for processing
                                if (!$latestChatDate->isSameDay($todayDate)) {
                                    $processingDate = $latestChatDate;
                                }
                                // Store the original date string from the latest message for filtering
                                $processingDateStr = $parsedDates->first()['message']['date'];
                            } else {
                                // If no valid date found, keep processingDate as today
                                // Try to find today's date string for filtering
                                $todayDateStr = $todayDate->format('d/m/y'); // Common format
                                $todayMessage = $parsedMessages->first(fn($msg) => str_contains($msg['date'], $todayDateStr));
                                if ($todayMessage) {
                                    $processingDateStr = $todayMessage['date'];
                                } else {
                                    // Attempt another format if the first fails
                                    $todayDateStrAlt = $todayDate->format('d.m.y');
                                    $todayMessageAlt = $parsedMessages->first(fn($msg) => str_contains($msg['date'], $todayDateStrAlt));
                                    if ($todayMessageAlt) {
                                        $processingDateStr = $todayMessageAlt['date'];
                                    }
                                }
                            }
                        } catch (\Exception $e) {
                            // Keep processingDate as today on error
                        }

                        // Filter messages for the processing date
                        $lastDayMessages = $parsedMessages;
                        if ($processingDateStr !== null) {
                            $lastDayMessages = $parsedMessages->filter(function ($msg) use ($processingDateStr) {
                                return $msg['date'] === $processingDateStr;
                            })->values();
                        } else {
                            // If we couldn't determine a date string, maybe show a warning?
                            // For now, we might process all messages, which isn't ideal.
                            // Or maybe filter by the carbon date object if possible?
                            // Let's filter by Carbon date object for robustness
                            $lastDayMessages = $parsedMessages->filter(function ($msg) use ($processingDate) {
                                $dateStr = $msg['date'];
                                preg_match('/(\d{1,2})[\/\.](\d{1,2})[\/\.](\d{2,4})/', $dateStr, $dateParts);
                                if (count($dateParts) >= 4) {
                                    $day = (int)$dateParts[1];
                                    $month = (int)$dateParts[2];
                                    $year = (int)$dateParts[3];
                                    if ($year < 100) $year += 2000;
                                    try {
                                        $msgDate = \Carbon\Carbon::createFromDate($year, $month, $day)->startOfDay();
                                        return $msgDate->isSameDay($processingDate);
                                    } catch (\Exception $e) {
                                        return false;
                                    }
                                }
                                return false;
                            });
                        }

                        // Extract unique users who sent media
                        $mediaSubmitters = $lastDayMessages
                            ->filter(function ($msg) {
                                // Match any media omitted text in multiple languages and formats
                                $mediaTexts = [
                                    '<Media omitted>',
                                    'Media omitted',
                                    '<Médias omis>',
                                    'Médias omis',
                                    '<تم استبعاد الوسائط>',
                                    'تم استبعاد الوسائط'
                                ];

                                foreach ($mediaTexts as $mediaText) {
                                    if (stripos($msg['text'], $mediaText) !== false) {
                                        return true;
                                    }
                                }
                                return false;
                            })
                            ->pluck('author')
                            ->unique()
                            ->values();

                        // Extract users who sent text (not media)
                        $textSubmitters = $lastDayMessages
                            ->filter(function ($msg) {
                                $mediaTexts = [
                                    '<Media omitted>',
                                    'Media omitted',
                                    '<Médias omis>',
                                    'Médias omis',
                                    '<تم استبعاد الوسائط>',
                                    'تم استبعاد الوسائط',
                                    'This message was deleted',
                                    'Ce message a été supprimé',
                                    'تم حذف هذه الرسالة'
                                ];

                                foreach ($mediaTexts as $mediaText) {
                                    if (stripos($msg['text'], $mediaText) !== false) {
                                        return false;
                                    }
                                }
                                return !empty(trim($msg['text']));
                            })
                            ->pluck('author')
                            ->unique()
                            ->values();

                        // Get submitters based on group's message submission type
                        $submitters = collect();
                        $submissionType = $this->ownerRecord->message_submission_type ?? MessageSubmissionType::Media;

                        if ($submissionType === MessageSubmissionType::Media || $submissionType === MessageSubmissionType::Both) {
                            $submitters = $submitters->merge($mediaSubmitters);
                        }

                        if ($submissionType === MessageSubmissionType::Text || $submissionType === MessageSubmissionType::Both) {
                            $submitters = $submitters->merge($textSubmitters);
                        }

                        $submitters = $submitters->unique()->values();
                        // Check if we have any submitters
                        if ($submitters->isEmpty()) {
                            $messageType = match ($submissionType) {
                                MessageSubmissionType::Media => 'وسائط صوتية',
                                MessageSubmissionType::Text => 'رسائل نصية',
                                MessageSubmissionType::Both => 'وسائط صوتية أو رسائل نصية',
                                default => 'وسائط صوتية'
                            };

                            Notification::make()
                                ->warning()
                                ->title("لم يتم العثور على طلاب أرسلوا {$messageType}.")
                                ->send();
                            return;
                        }

                        // Get ignored names/phones from group settings
                        $ignoredNamesPhones = $this->ownerRecord->ignored_names_phones ?? [];

                        // Make sure it's an array
                        if (!is_array($ignoredNamesPhones)) {
                            if (is_string($ignoredNamesPhones) && !empty($ignoredNamesPhones)) {
                                $ignoredNamesPhones = explode(',', $ignoredNamesPhones);
                            } else {
                                $ignoredNamesPhones = [];
                            }
                        }
                        // Get all students in this group
                        $students = $this->ownerRecord->students;
                        $attendanceDate = $processingDate->format('Y-m-d'); // Use determined date for DB
                        $presentCount = 0;
                        $notFoundCount = 0;
                        $notFoundNames = [];
                        $presentStudentIds = [];
                        // Process each submitter
                        foreach ($submitters as $submitterName) {
                            // Skip if the submitter is in the ignored list
                            if (!empty($ignoredNamesPhones) && in_array($submitterName, $ignoredNamesPhones)) {
                                continue;
                            }

                            $found = false;

                            // Clean up the name (remove phone labels, special characters, etc.)
                            $cleanName = preg_replace('/\s*\([^)]*\)/', '', $submitterName);
                            $cleanName = trim($cleanName);
                            // Extract phone number if it exists in the name
                            $phoneNumber = null;
                            try {
                                $potentialNumber = preg_replace('/[^0-9+]/', '', $submitterName);
                                if (!empty($potentialNumber)) {
                                    // Try parsing with common country codes or default (assuming Morocco)
                                    $parsedPhone = phone($potentialNumber, 'MA')->formatE164();
                                    if ($parsedPhone) {
                                        $phoneNumber = $parsedPhone;
                                    }
                                }
                            } catch (\Exception $e) {
                                // Ignore if parsing fails
                            }

                            // Try exact matching methods
                            $matchedStudent = null;

                            // 1. Try exact name match (case-insensitive)
                            $matchedStudent = $students->first(function ($student) use ($cleanName, $submitterName) {
                                // Normalize: trim and convert to lowercase
                                $dbName = mb_strtolower(trim($student->name));
                                $chatName = mb_strtolower($cleanName);
                                $originalChatName = mb_strtolower($submitterName);

                                // Compare the normalized names for exact match
                                return $dbName === $chatName || $dbName === $originalChatName;
                            });

                            // 2. Try phone number match (E.164 format) - if name match failed
                            if (!$matchedStudent && $phoneNumber) {
                                $matchedStudent = $students->first(function ($student) use ($phoneNumber) {
                                    try {
                                        $studentPhone = phone($student->phone, 'MA')->formatE164();
                                        return $studentPhone === $phoneNumber;
                                    } catch (\Exception $e) {
                                        return false;
                                    }
                                });
                            }


                            // If we found a match, mark the student as present
                            if ($matchedStudent) {
                                $found = true;
                                $presentCount++;
                                $presentStudentIds[] = $matchedStudent->id;

                                // Check if student already has a progress record for the processing date
                                if ($matchedStudent->progresses->where('date', $attendanceDate)->count() == 0) {
                                    // Create new progress record
                                    $matchedStudent->progresses()
                                        ->create([
                                            'created_by' => auth()->id(),
                                            'date' => $attendanceDate,
                                            'status' => 'memorized',
                                            'page_id' => null,
                                            'lines_from' => null,
                                            'lines_to' => null,
                                        ]);
                                } else {
                                    // Update existing progress record
                                    $matchedStudent->progresses()->where('date', $attendanceDate)->update([
                                        'status' => 'memorized',
                                    ]);
                                }
                            }

                            if (!$found) {
                                $notFoundCount++;
                                $notFoundNames[] = $submitterName;
                            }
                        }
                        // Register the rest as absent if option is enabled
                        if (!empty($data['register_rest_absent'])) {
                            $absentStudents = $students->whereNotIn('id', $presentStudentIds);
                            foreach ($absentStudents as $student) {
                                // Skip ignored
                                if (!empty($ignoredNamesPhones) && (in_array($student->name, $ignoredNamesPhones) || in_array(phone($student->phone, 'MA')->formatE164(), $ignoredNamesPhones))) {
                                    continue;
                                }
                                // Only mark as absent if not already present for the processing date
                                if ($student->progresses->where('date', $attendanceDate)->count() == 0) {
                                    $student->progresses()->create([
                                        'created_by' => auth()->id(),
                                        'date' => $attendanceDate,
                                        'status' => 'absent',
                                        'page_id' => null,
                                        'lines_from' => null,
                                        'lines_to' => null,
                                    ]);
                                } else {
                                    $student->progresses()->where('date', $attendanceDate)->update([
                                        'status' => 'absent',
                                    ]);
                                }
                            }
                        }

                        // Delete uploaded file
                        if (file_exists($storagePath)) {
                            unlink($storagePath);
                        }
                        if ($tempTxtPath && file_exists($tempTxtPath)) {
                            unlink($tempTxtPath);
                        }

                        // Show notification with results
                        if ($presentCount > 0) {
                            Notification::make()
                                ->success()
                                ->title('تم تسجيل حضور ' . $presentCount . ' طالب بنجاح')
                                ->seconds(5)
                                ->send();
                        }

                        if ($notFoundCount > 0) {
                            $message = 'لم يتم العثور على ' . $notFoundCount . ' طالب في المجموعة: ' .
                                implode('، ', $notFoundNames);


                            Notification::make()
                                ->warning()
                                ->title($message)
                                ->seconds(10)
                                ->send();
                        }
                    }),
            ])
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
                    BulkAction::make('delete_todays_progress')
                        ->label('حذف حضور اليوم')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalSubmitActionLabel('تأكيد الحذف')
                        ->action(function () {
                            $students = $this->selectedTableRecords;
                            $today = now()->format('Y-m-d');
                            $deletedCount = 0;
                            foreach ($students as $studentId) {
                                $student = \App\Models\Student::find($studentId);
                                if ($student) {
                                    $deletedCount += $student->progresses()->where('date', $today)->delete();
                                }
                            }
                            \Filament\Notifications\Notification::make()
                                ->title('تم حذف حضور اليوم لـ ' . $deletedCount . ' طالب')
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
                BulkAction::make('set_as_absent')
                    ->label('غائبين')
                    ->color('danger')
                    ->icon('heroicon-o-exclamation-circle')
                    ->requiresConfirmation()
                    ->modalSubmitActionLabel('تأكيد')
                    ->form([
                        Toggle::make('with_reason')
                            ->label('غياب بعذر')
                            ->reactive()
                            ->default(false),
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
                                    'with_reason' => $data['with_reason'] ?? false,
                                    'comment' => $data['send_msg'] ? 'message_sent' : null,
                                    'page_id' => null,
                                    'lines_from' => null,
                                    'lines_to' => null,
                                ]);
                            } else {
                                $student->progresses()->where('date', $selectedDate)->update([
                                    'status' => 'absent',
                                    'with_reason' => $data['with_reason'] ?? false,
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


        // Check if the group has a selected message template
        if ($ownerRecord->messageTemplates->count() > 0) {
            $message = Core::processMessageTemplate($ownerRecord->messageTemplates->first()->content, $record, $ownerRecord);
        }
        // Check if there's a default template in the group's message templates
        else if ($defaultTemplate = $ownerRecord->messageTemplates()->where('is_default', true)->first()) {
            $message = Core::processMessageTemplate($defaultTemplate->content, $record, $ownerRecord);
        }
        // Use built-in templates based on group type
        else {

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

    /**
     * Get students sorted by attendance status and remark
     * 
     * @param \App\Models\Group $group The group to get students from
     * @param string|null $date The date to check attendance for (defaults to today)
     * @return \Illuminate\Support\Collection Sorted collection of students
     */
    public static function getSortedStudentsForAttendanceReport(Group $group, ?string $date = null): \Illuminate\Support\Collection
    {
        $date = $date ?? now()->format('Y-m-d');

        $students = $group->students()
            ->withCount(['progresses as attendance_count' => function ($query) use ($date) {
                $query->where('date', $date)->where('status', 'memorized');
            }])
            ->with(['today_progress' => function ($query) use ($date) {
                $query->where('date', $date);
            }])
            ->with(['progresses' => function ($query) {
                $query->latest('date')->limit(30);
            }])
            ->get();

        // First sort by today's attendance status (present first)
        $students = $students->sortByDesc('attendance_count');

        // Then prepare and sort by attendance remark within each group
        $presentStudents = $students->where('attendance_count', '>', 0)->values();
        $absentStudents = $students->where('attendance_count', '=', 0)->values();

        // Sort present students by attendance remark
        $sortedPresent = $presentStudents->sortBy(function ($student) {
            $remarkScores = [
                'ممتاز' => 1,
                'جيد' => 2,
                'حسن' => 3,
                'لا بأس به' => 4,
                'متوسط' => 5,
                'ضعيف' => 6
            ];
            return $remarkScores[$student->attendanceRemark['label']] ?? 7;
        })->values();

        // Sort absent students by attendance remark
        $sortedAbsent = $absentStudents->sortBy(function ($student) {
            $remarkScores = [
                'ممتاز' => 1,
                'جيد' => 2,
                'حسن' => 3,
                'لا بأس به' => 4,
                'متوسط' => 5,
                'ضعيف' => 6
            ];
            return $remarkScores[$student->attendanceRemark['label']] ?? 7;
        })->values();

        // Combine the sorted collections
        return $sortedPresent->concat($sortedAbsent);
    }
}
