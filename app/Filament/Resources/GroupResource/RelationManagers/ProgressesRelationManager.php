<?php

namespace App\Filament\Resources\GroupResource\RelationManagers;

use App\Classes\Core;
use App\Filament\Exports\ProgressExporter;
use App\Helpers\ProgressFormHelper;
use App\Models\Student;
use App\Models\StudentDisconnection;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Colors\Color;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ExportAction;
use Filament\Tables\Actions\ExportBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\IconColumn\IconColumnSize;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\ActionsPosition;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ProgressesRelationManager extends RelationManager
{
    protected static string $relationship = 'progresses';

    protected static bool $isLazy = false;

    protected static ?string $title = 'التقدم';

    protected static ?string $navigationLabel = 'التقدم';

    protected static ?string $modelLabel = 'تقدم';

    protected static ?string $pluralModelLabel = 'تقدمات';

    public $dateFrom;

    public $dateTo;

    public function form(Form $form): Form
    {
        return $form->schema(
            ProgressFormHelper::getProgressFormSchema(group: $this->ownerRecord)
        );
    }

    public function table(Table $table): Table
    {
        $dateFrom = $this->dateFrom ?? now()->subDays(4)->format('Y-m-d');
        $dateTo = $this->dateTo ?? now()->format('Y-m-d');
        // Calculate status per day for each student
        $statusPerDay = $this->ownerRecord->students

            ->mapWithKeys(function ($student) use ($dateFrom, $dateTo) {
                return [
                    $student->id => $student->progresses
                        ->whereBetween('date', [$dateFrom, $dateTo])
                        ->groupBy('date')
                        ->map(function ($group) {
                            return $group->groupBy('status');
                        }),
                ];
            });
        // dd($statusPerDay);
        // Prepare columns for each date within the range
        $dateRange = new \DatePeriod(
            new \DateTime($dateFrom),
            new \DateInterval('P1D'),
            (new \DateTime($dateTo))->modify('+1 day')
        );

        $statusColumns = collect();
        foreach ($dateRange as $date) {
            $formattedDate = $date->format('Y-m-d');
            $day = $date->format('d/m');
            $statusColumns->push(

                IconColumn::make("status_day_{$formattedDate}")
                    ->getStateUsing(function ($record) use ($statusPerDay, $formattedDate) {
                        if ($record->id && isset($statusPerDay[$record->id][$formattedDate])) {
                            $progress = $statusPerDay[$record->id][$formattedDate]->first()[0];
                            $status = $progress->status;

                            // If status is absent and with_reason is true, return a special status
                            if ($status === 'absent' && $progress->with_reason) {
                                return 'absent_with_reason';
                            }

                            return $status;
                        }

                        return null;
                    })
                    ->color(function ($state) {
                        return match ($state) {
                            'memorized' => 'success',
                            'absent' => 'danger',
                            'absent_with_reason' => 'info',
                            default => 'muted'
                        };
                    })
                    ->size(IconColumnSize::Large)
                    ->default('unknown')
                    ->icon(function ($state) {
                        return match ($state) {
                            'memorized' => 'heroicon-o-check-circle',
                            'absent' => 'heroicon-o-x-circle',
                            'absent_with_reason' => 'heroicon-o-exclamation-circle',
                            default => 'heroicon-o-minus-circle',
                            null => 'heroicon-o-minus-circle',
                        };
                    })
                    ->label($day)
            );
        }

        return $table
            ->deferFilters()
            ->columns(
                [
                    TextColumn::make('name')
                        ->getStateUsing(function ($record, $rowLoop) {

                            return $rowLoop->iteration . '. ' . $record->name;
                        })
                        ->label('الطالب'),
                    ...$statusColumns->toArray(),
                ]
            )
            ->paginated(false)
            ->query(function () use ($dateFrom, $dateTo) {
                $query = $this->ownerRecord->students()
                    ->withCount(['progresses as attendance_count' => function ($query) use ($dateFrom, $dateTo) {
                        $query->whereBetween('date', [$dateFrom, $dateTo])
                            ->where('status', 'memorized');
                    }])
                    ->orderByDesc('attendance_count');

                return $query;
            })
            ->headerActions([
                ExportAction::make()
                    ->label('تصدير الكل')
                    ->visible(fn() => Auth::user()->isAdministrator())
                    ->exporter(ProgressExporter::class)
                    ->successNotification(null)
                    ->icon('heroicon-o-arrow-down-tray'),
                Action::make('export_progress_table')
                    ->label('تصدير التقرير')
                    ->icon('heroicon-o-share')
                    ->color('success')
                    ->action(function () {
                        $dateFrom = $this->dateFrom ?? now()->subDays(4)->format('Y-m-d');
                        $dateTo = $this->dateTo ?? now()->format('Y-m-d');

                        // Get date range
                        $dateRange = new \DatePeriod(
                            new \DateTime($dateFrom),
                            new \DateInterval('P1D'),
                            (new \DateTime($dateTo))->modify('+1 day')
                        );

                        // Calculate status per day for each student
                        $statusPerDay = $this->ownerRecord->students
                            ->mapWithKeys(function ($student) use ($dateFrom, $dateTo) {
                                return [
                                    $student->id => $student->progresses
                                        ->whereBetween('date', [$dateFrom, $dateTo])
                                        ->groupBy('date')
                                        ->map(function ($group) {
                                            return $group->groupBy('status');
                                        }),
                                ];
                            });

                        $students = $this->ownerRecord->students()
                            ->withCount(['progresses as attendance_count' => function ($query) use ($dateFrom, $dateTo) {
                                $query->whereBetween('date', [$dateFrom, $dateTo])
                                    ->where('status', 'memorized');
                            }])
                            ->orderByDesc('attendance_count')
                            ->get();

                        $html = view('components.progress-export-table', [
                            'students' => $students,
                            'group' => $this->ownerRecord,
                            'dateRange' => $dateRange,
                            'statusPerDay' => $statusPerDay,
                        ])->render();

                        $this->dispatch('export-table', [
                            'html' => $html,
                            'groupName' => $this->ownerRecord->name
                        ]);
                    }),
            ])
            ->filters([
                Filter::make('date')
                    ->form([
                        DatePicker::make('date_from')
                            ->label('من تاريخ')
                            ->reactive()
                            ->afterStateUpdated(fn($state) => $this->dateFrom = $state ?? now()->subDays(4)->format('Y-m-d'))
                            ->default(now()->subDays(4)->format('Y-m-d')),
                        DatePicker::make('date_to')
                            ->reactive()
                            ->label('إلى تاريخ')
                            ->afterStateUpdated(fn($state) => $this->dateTo = $state ?? now()->format('Y-m-d'))
                            ->default(now()->format('Y-m-d')),
                    ]),
                Filter::make('present_number')
                    ->label('فلتر التقدم حسب عدد أيام الحضور')
                    ->form([
                        TextInput::make('number')
                            ->label('عدد أيام الحضور')
                            ->numeric()
                            ->minValue(0)
                            ->required(),
                    ])
                    ->modifyQueryUsing(function ($query, array $data) {
                        if ($data['number']) {

                            return $query->whereHas('progresses', function ($subQuery) use ($data) {
                                $subQuery->select('student_id')
                                    ->where('status', 'memorized')
                                    ->groupBy('student_id')
                                    ->havingRaw('COUNT(*) >= ?', [$data['number']]);
                            });
                        }
                    }),
                Filter::make('absent_number')
                    ->label('فلتر التقدم حسب عدد أيام الغياب')
                    ->form([
                        TextInput::make('number')
                            ->label('عدد أيام الغياب')
                            ->numeric()
                            ->minValue(0)
                            ->required(),
                    ])
                    ->modifyQueryUsing(function ($query, array $data) {
                        if (isset($data['number'])) {
                            return $query->whereHas('progresses', function ($subQuery) use ($data) {
                                $subQuery->select('student_id')
                                    ->where('status', 'absent')
                                    ->groupBy('student_id')
                                    ->havingRaw('COUNT(*) >= ?', [$data['number']]);
                            });
                        }
                    }),

            ])

            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->label('تصدير')
                        ->exporter(ProgressExporter::class)
                        ->icon('heroicon-o-arrow-down-tray'),
                    Tables\Actions\BulkAction::make('add_to_disconnection')
                        ->label('إضافة إلى قائمة الانقطاع')
                        ->icon('heroicon-o-user-minus')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('إضافة الطلاب المحددين إلى قائمة الانقطاع')
                        ->modalDescription('سيتم إضافة الطلاب المحددين إلى قائمة الطلاب المنقطعين.')
                        ->action(function ($records) {
                            $addedCount = 0;
                            
                            foreach ($records as $student) {
                                if (!StudentDisconnection::where('student_id', $student->id)->exists()) {
                                    $disconnectionDate = $student->getDisconnectionDateAttribute();
                                    
                                    if ($disconnectionDate) {
                                        StudentDisconnection::create([
                                            'student_id' => $student->id,
                                            'group_id' => $student->group_id,
                                            'disconnection_date' => $disconnectionDate,
                                        ]);
                                        $addedCount++;
                                    }
                                }
                            }
                            
                            if ($addedCount > 0) {
                                Notification::make()
                                    ->title('تم إضافة الطلاب إلى قائمة الانقطاع')
                                    ->body("تم إضافة {$addedCount} طالب إلى قائمة الانقطاع.")
                                    ->success()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('لم يتم إضافة أي طالب')
                                    ->body('جميع الطلاب المحددين موجودين بالفعل في قائمة الانقطاع أو لا يوجد لديهم تاريخ انقطاع صالح.')
                                    ->warning()
                                    ->send();
                            }
                        }),
                    Tables\Actions\DeleteBulkAction::make()
                        ->modalHeading('حذف الطلاب المحددين')
                        ->modalDescription('هل أنت متأكد من حذف الطلاب المحددين؟ سيتم حذف جميع بيانات التقدم والحضور المرتبطة بهم.')
                        ->modalSubmitActionLabel('نعم، احذف')
                        ->modalCancelActionLabel('إلغاء'),
                ]),
            ])
            ->actions([
                Tables\Actions\Action::make('send_whatsapp_msg')
                    ->color('success')
                    ->iconButton()
                    ->icon('heroicon-o-chat-bubble-oval-left')
                    ->label('إرسال رسالة واتساب')
                    ->url(function (Student $record) {
                        return StudentsRelationManager::getWhatsAppUrl($record, $this->ownerRecord);
                    }, true),
            ])
            ->actionsPosition(ActionsPosition::BeforeColumns);
    }

    public function isReadOnly(): bool
    {
        return ! $this->ownerRecord->managers->contains(auth()->user());
    }

    public function getDateFrom(): string
    {
        return $this->dateFrom;
    }

    public function setDateFrom(string $dateFrom): void
    {
        $this->dateFrom = $dateFrom;
    }

    public function getDateTo(): string
    {
        return $this->dateTo;
    }

    public function setDateTo(string $dateTo): void
    {
        $this->dateTo = $dateTo;
    }

    public function headerActions(): array
    {
        return [
            Tables\Actions\CreateAction::make(),
            Action::make('make_others_as_absent')
                ->visible(fn() => $this->ownerRecord->managers->contains(auth()->user()))
                ->label('تسجيل البقية كغائبين اليوم')
                ->color('danger')
                ->action(function () {
                    $selectedDate = $this->tableFilters['date']['value'] ?? now()->format('Y-m-d');
                    $this->ownerRecord->students->filter(function ($student) use ($selectedDate) {
                        return $student->progresses->where('date', $selectedDate)->count() == 0;
                    })->each(function ($student) use ($selectedDate) {
                        $student->progresses()->create([
                            'date' => $selectedDate,
                            'status' => 'absent',
                            'comment' => 'message_sent',
                            'page_id' => null,
                            'lines_from' => null,
                            'lines_to' => null,
                        ]);
                        Notification::make()
                            ->title('تم تسجيل الطالب ' . $student->name . ' كغائب اليوم')
                            ->color('success')
                            ->icon('heroicon-o-check-circle')
                            ->send();
                        if ($selectedDate == now()->format('Y-m-d')) {
                            Core::sendMessageToStudent($student);
                        }
                    });
                }),
            Action::make('group')
                ->label('تسجيل تقدم جماعي')
                ->visible(fn() => $this->ownerRecord->managers->contains(auth()->user()))
                ->color(Color::Teal)
                ->form(function (Get $get) {
                    $students = $this->ownerRecord->students->filter(function ($student) {
                        return $student->progresses->where('date', now()->format('Y-m-d'))->count() == 0;
                    })->pluck('name', 'id');

                    return [
                        Grid::make()
                            ->schema([
                                Select::make('students')
                                    ->label('الطلاب')
                                    ->options(function (Get $get) {
                                        return $this->ownerRecord->students->filter(function ($student) use ($get) {
                                            return $student->progresses->where('date', $get('date'))->count() == 0;
                                        })->pluck('name', 'id');
                                    })
                                    ->required()
                                    ->default(fn() => $students->keys()->toArray())
                                    ->multiple(),
                                DatePicker::make('date')
                                    ->label('التاريخ')
                                    ->reactive()
                                    ->default(now()->format('Y-m-d'))
                                    ->required(),
                                ToggleButtons::make('status')
                                    ->label('الحالة')
                                    ->inline()
                                    ->reactive()
                                    ->icons([
                                        'memorized' => 'heroicon-o-check-circle',
                                        'absent' => 'heroicon-o-x-circle',
                                    ])
                                    ->grouped()
                                    ->default('memorized')
                                    ->colors([
                                        'memorized' => 'primary',
                                        'absent' => 'danger',
                                    ])
                                    ->options([
                                        'memorized' => 'أتم الحفظ',
                                        'absent' => 'غائب',
                                    ])
                                    ->required(),
                                ToggleButtons::make('comment')
                                    ->label('التعليق')
                                    ->inline()
                                    ->default('message_sent')
                                    ->colors([
                                        'message_sent' => 'success',
                                        'call_made' => 'warning',
                                    ])
                                    ->options([
                                        'message_sent' => 'تم إرسال الرسالة',
                                        'call_made' => 'تم الاتصال',
                                    ]),
                            ]),
                        MarkdownEditor::make('notes')
                            ->label('ملاحظات')
                            ->columnSpanFull()
                            ->placeholder('أدخل ملاحظاتك هنا'),
                    ];
                })
                ->action(function (array $data) {
                    foreach ($data['students'] as $studentId) {
                        $student = Student::find($studentId);
                        $student->progresses()->create([
                            'date' => $data['date'],
                            'status' => $data['status'],
                            'comment' => $data['comment'],
                            'page_id' => $data['page_id'] ?? null,
                            'lines_from' => $data['lines_from'] ?? null,
                            'lines_to' => $data['lines_to'] ?? null,
                            'notes' => $data['notes'],
                        ]);
                    }
                }),
        ];
    }
}
