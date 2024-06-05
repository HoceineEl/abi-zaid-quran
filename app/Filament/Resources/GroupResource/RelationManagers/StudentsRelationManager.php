<?php

namespace App\Filament\Resources\GroupResource\RelationManagers;

use App\Classes\Core;
use App\Helpers\ProgressFormHelper;
use App\Models\Group;
use App\Models\Progress;
use App\Models\Student;
use Filament\Actions\CreateAction;
use Filament\Forms;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\ActionSize;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup as ActionsActionGroup;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\CreateAction as ActionsCreateAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Header;
use Illuminate\Database\Eloquent\Model;

class StudentsRelationManager extends RelationManager
{
    protected static string $relationship = 'students';

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
                TextColumn::make('name')
                    ->icon(function (Student $record) {
                        $ProgToday = $record->progresses->where('date', now()->format('Y-m-d'))->first();
                        if ($ProgToday) {
                            return $ProgToday->status === 'memorized' ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle';
                        }
                    })
                    ->color(function (Student $record) {
                        $ProgToday = $record->progresses()->where('date', now()->format('Y-m-d'))->first();
                        if ($ProgToday) {
                            return $ProgToday->status === 'memorized' ? 'success' : 'danger';
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
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->slideOver()
                    ->visible(fn () => $this->ownerRecord->managers->contains(auth()->user()))
                    ->modalWidth('4xl'),
            ])
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
                    ->url(fn ($record) => "https://wa.me/{$record->phone}?text=" . urlencode('السلام عليكم'), true),
                Tables\Actions\Action::make('progress')
                    ->icon('heroicon-o-chart-pie')
                    ->color('success')
                    ->modal()
                    ->disabled(fn ($record) => Progress::where('student_id', $record->id)->whereDate('date', now()->format('Y-m-d'))->exists())
                    ->color(fn ($record) => Progress::where('student_id', $record->id)->whereDate('date', now()->format('Y-m-d'))->exists() ? 'gray' : 'success')
                    ->label(fn ($record) => Progress::where('student_id', $record->id)->whereDate('date', now()->format('Y-m-d'))->exists() ? 'تم إضافة التقدم' : 'إضافة التقدم')
                    ->slideOver()
                    ->form(function (Model $student) {
                        return ProgressFormHelper::getProgressFormSchema($student);
                    })
                    ->action(function (array $data, Model $student) {
                        $data['created_by'] = auth()->id();
                        $data['student_id'] = $student->id;
                        Progress::create($data);
                        Notification::make('added')
                            ->title('تم إضافة التقدم بنجاح')
                            ->success()->send();
                    }),

            ])
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
                        ->form([
                            Toggle::make('send_msg')
                                ->label('إرسال رسالة تذكير')
                                ->reactive()
                                ->default(false),
                            Textarea::make('message')
                                ->hint('السلام عليكم وإسم الطالب سيتم إضافته تلقائياً في  الرسالة.')
                                ->reactive()
                                ->hidden(fn (Get $get) => !$get('send_msg'))
                                ->default('لم ترسلوا الواجب المقرر اليوم، لعل المانع خير.')
                                ->label('الرسالة')
                                ->required()
                        ])
                        ->visible(fn () => Progress::where('date', now()->format('Y-m-d'))->count() !== $this->ownerRecord->students->count() && $this->ownerRecord->managers->contains(auth()->user()))
                        ->action(function (array $data) {
                            $selectedDate = $this->tableFilters['date']['value'] ?? now()->format('Y-m-d');
                            $this->ownerRecord->students->filter(function ($student) use ($selectedDate) {
                                return $student->progresses->where('date', $selectedDate)->count() == 0;
                            })->each(function ($student) use ($selectedDate, $data) {
                                $student->progresses()->create([
                                    'date' => $selectedDate,
                                    'status' => 'absent',
                                    'comment' => 'message_sent',
                                    'page_id' => null,
                                    'lines_from' => null,
                                    'lines_to' => null,
                                ]);
                                if ($data['send_msg']) {
                                    $msg = $data['message'];
                                    Core::sendSpecifMessageToStudent($student, $msg);
                                }
                                Notification::make()
                                    ->title('تم تسجيل الطالب ' . $student->name . ' كغائب اليوم')
                                    ->color('success')
                                    ->icon('heroicon-o-check-circle')
                                    ->send();
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
                                ->required()
                        ])
                        ->visible(fn () => $this->ownerRecord->managers->contains(auth()->user()))
                        ->action(function (array $data) {
                            $selectedDate = $this->tableFilters['date']['value'] ?? now()->format('Y-m-d');
                            $this->ownerRecord->students->filter(function ($student) use ($selectedDate) {
                                return $student->progresses->where('date', $selectedDate)->count() == 0;
                            })->each(function ($student) use ($selectedDate, $data) {
                                if ($selectedDate == now()->format('Y-m-d')) {
                                    $msg = $data['message'];
                                    Core::sendSpecifMessageToStudent($student, $msg);
                                }
                                Notification::make()
                                    ->title('تم إرسال رسالة للطالب ' . $student->name . ' بنجاح')
                                    ->color('success')
                                    ->icon('heroicon-o-check-circle')
                                    ->send();
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
                                ->required()
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
                                Notification::make()
                                    ->title('تم إرسال رسالة للطالب ' . $student->name . ' بنجاح')
                                    ->color('success')
                                    ->icon('heroicon-o-check-circle')
                                    ->send();
                            });
                        }),
                ])
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
                                $data = ProgressFormHelper::calculateNextProgress($student);
                                if ($student->progresses->where('date', now()->format('Y-m-d'))->count() == 0) {
                                    $student->progresses()->create([
                                        'created_by' => auth()->id(),
                                        'date' => now()->format('Y-m-d'),
                                        'page_id' => $data['page_id'],
                                        'lines_from' => $data['lines_from'],
                                        'lines_to' => $data['lines_to'],
                                        'status' => 'memorized',
                                    ]);
                                    Notification::make('added')
                                        ->title("أضيف $student->name")
                                        ->success();
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
                                ->required()
                        ])
                        ->visible(fn () => $this->ownerRecord->managers->contains(auth()->user()))
                        ->action(function (array $data) {
                            $students = $this->selectedTableRecords;
                            foreach ($students as $studentId) {
                                $student = Student::find($studentId);
                                $msg = $data['message'];
                                Core::sendSpecifMessageToStudent($student, $msg);
                                Notification::make()
                                    ->title('تم إرسال رسالة للطالب ' . $student->name . ' بنجاح')
                                    ->color('success')
                                    ->icon('heroicon-o-check-circle')
                                    ->send();
                            }
                        })->deselectRecordsAfterCompletion(),
                ])
            ]);
    }

    public function isReadOnly(): bool
    {
        return  !$this->ownerRecord->managers->contains(auth()->user());
    }
}
