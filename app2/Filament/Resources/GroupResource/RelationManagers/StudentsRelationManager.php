<?php

namespace App\Filament\Resources\GroupResource\RelationManagers;

use App\Classes\Core;
use App\Helpers\ProgressFormHelper;
use App\Models\Progress;
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
use Illuminate\Database\Eloquent\Model;

class StudentsRelationManager extends RelationManager
{
    protected static string $relationship = 'students';

    protected static bool $isLazy = false;

    protected static ?string $title = 'الطلاب';

    protected static ?string $navigationLabel = 'الطلاب';

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
                TextColumn::make('order_no')
                    ->getStateUsing(function ($record) {
                        $id = $record->id;
                        $idInGroup = $this->ownerRecord->students->pluck('id')->search($id);
                        if ($record->order_no != ($idInGroup + 1)) {
                            $record->update(['order_no' => $idInGroup + 1]);
                            $record->save();
                        }

                        return $record->order_no;
                    })
                    ->label('الرقم'),
                TextColumn::make('name')
                    ->icon(function (Student $record) {
                        $ProgToday = $record->progresses->where('date', now()->format('Y-m-d'))->first();
                        if ($ProgToday) {
                            return $ProgToday->status === 'memorized' ? 'heroicon-o-check-circle' : ($ProgToday->status === 'absent' ? 'heroicon-o-exclamation-circle' : 'heroicon-o-information-circle');
                        }
                    })
                    ->color(function (Student $record) {
                        $ProgToday = $record->progresses()->where('date', now()->format('Y-m-d'))->first();
                        if ($ProgToday) {
                            return $ProgToday->status === 'memorized' ? 'success' : ($ProgToday->status === 'absent' ? 'danger' : 'warning');
                        }
                    })
                    ->label('الاسم'),
                TextColumn::make('phone')
                    ->url(fn ($record) => "tel:{$record->phone}")
                    ->badge()
                    ->icon(fn ($record) => $record->needsCall() ? 'heroicon-o-exclamation-circle' : 'heroicon-o-check-circle')
                    ->color(fn (Student $record) => $record->needsCall() ? 'danger' : 'success')
                    ->label('رقم الهاتف'),
                TextColumn::make('sex')->label('الجنس')
                    ->formatStateUsing(function ($state) {
                        return match ($state) {
                            'male' => 'ذكر',
                            'female' => 'أنثى',
                        };
                    }),
                TextColumn::make('city')->label('المدينة'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->slideOver()
                    ->visible(fn () => $this->ownerRecord->managers->contains(auth()->user()))
                    ->modalWidth('4xl'),
            ])
            ->reorderable('order_no', true)
            ->defaultSort('order_no')
            ->actions([
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
                    ->url(function ($record) {
                        $number = $record->phone;
                        if (substr($number, 0, 2) == '06' || substr($number, 0, 2) == '07') {
                            $number = '+212' . substr($number, 1);
                        }
                        $message = "السلام عليكم ورحمة الله وبركاته أخي الطالب {$record->name}، نذكرك بالواجب المقرر اليوم، لعل المانع خير.";

                        if (str_contains($this->ownerRecord->type, 'سرد')) {
                            $message = "السلام عليكم ورحمة الله وبركاته،
أخي الطالب **student_name**،
نذكرك بواجب اليوم من السرد، المرجو المبادرة قبل غلق المجموعة زادكم الله حرصا";
                            $message = str_replace('student_name', $record->name, $message);
                        }

                        return "https://wa.me/{$number}?text=" . urlencode($message);
                    }, true),
                // Tables\Actions\Action::make('progress')
                //     ->icon('heroicon-o-chart-pie')
                //     ->color('success')
                //     ->modal()
                //     ->disabled(fn ($record) => Progress::where('student_id', $record->id)->whereDate('date', now()->format('Y-m-d'))->exists())
                //     ->color(fn ($record) => Progress::where('student_id', $record->id)->whereDate('date', now()->format('Y-m-d'))->exists() ? 'gray' : 'success')
                //     ->label(fn ($record) => Progress::where('student_id', $record->id)->whereDate('date', now()->format('Y-m-d'))->exists() ? 'تم إضافة التقدم' : 'إضافة التقدم')
                //     ->slideOver()
                //     ->form(function (Model $student) {
                //         return ProgressFormHelper::getProgressFormSchema($student);
                //     })
                //     ->action(function (array $data, Model $student) {
                //         $data['created_by'] = auth()->id();
                //         $data['student_id'] = $student->id;
                //         Progress::create($data);
                //         Notification::make('added')
                //             ->title('تم إضافة التقدم بنجاح')
                //             ->success()->send();
                //     }),

            ], ActionsPosition::BeforeColumns)
            ->paginated(false)
            ->headerActions([
                ActionsActionGroup::make([
                    ActionsCreateAction::make()
                        ->label('إضافة طالب')
                        ->icon('heroicon-o-plus-circle')
                        ->visible(fn () => $this->ownerRecord->managers->contains(auth()->user()))
                        ->slideOver(),
                    Action::make('make_others_as_absent')
                        ->label('تسجيل البقية كغائبين')
                        ->color('danger')
                        ->icon('heroicon-o-exclamation-circle')
                        ->form([
                            Toggle::make('send_msg')
                                ->label('تأكيد إرسال رسالة تذكير')
                                ->reactive()
                                ->default(false),
                            Textarea::make('message')
                                ->hint('السلام عليكم وإسم الطالب سيتم إضافته تلقائياً في  الرسالة.')
                                ->reactive()
                                ->hidden(fn (Get $get) => !$get('send_msg'))
                                ->default('لم ترسلوا الواجب المقرر اليوم، لعل المانع خير.')
                                ->label('الرسالة')
                                ->required(),
                        ])
                        ->modalSubmitActionLabel('تأكيد')
                        ->visible(fn () => $this->ownerRecord->managers->contains(auth()->user()))
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
                        ->visible(fn () => $this->ownerRecord->managers->contains(auth()->user()))
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
                        ->visible(fn () => $this->ownerRecord->managers->contains(auth()->user()))
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
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('set_prgress')
                        ->label('تسجيلهم كحاضرين')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn () => $this->ownerRecord->managers->contains(auth()->user()))
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
                        ->visible(fn () => $this->ownerRecord->managers->contains(auth()->user()))
                        ->action(function (array $data) {
                            $students = $this->selectedTableRecords;
                            foreach ($students as $studentId) {
                                $student = Student::find($studentId);
                                $msg = $data['message'];
                                Core::sendSpecifMessageToStudent($student, $msg);
                            }
                        })->deselectRecordsAfterCompletion(),
                    BulkAction::make('set_as_absent')
                        ->label('تسجيلهم كغائبين')
                        ->color('danger')
                        ->icon('heroicon-o-exclamation-circle')
                        ->modalSubmitActionLabel('تأكيد')
                        ->form([
                            Toggle::make('send_msg')
                                ->label('تأكيد إرسال رسالة تذكير')
                                ->reactive()
                                ->default(false),
                            Textarea::make('message')
                                ->hint('السلام عليكم وإسم الطالب سيتم إضافته تلقائياً في  الرسالة.')
                                ->reactive()
                                ->hidden(fn (Get $get) => !$get('send_msg'))
                                ->default('لم ترسلوا الواجب المقرر اليوم، لعل المانع خير.')
                                ->label('الرسالة')
                                ->required(),
                        ])
                        ->visible(fn () => $this->ownerRecord->managers->contains(auth()->user()))
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
                ]),
            ]);
    }

    public function isReadOnly(): bool
    {
        return !$this->ownerRecord->managers->contains(auth()->user());
    }
}
