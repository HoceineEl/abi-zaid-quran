<?php

namespace App\Filament\Association\Resources\GroupResource\RelationManagers;

use App\Enums\Troubles;
use App\Models\Attendance;
use App\Models\Memorizer;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\BulkAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Filament\Tables\Enums\ActionsPosition;
use Filament\Tables\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Section;
use App\Enums\MemorizationScore;
use App\Filament\Association\Resources\GroupResource;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ToggleButtons;
use Filament\Support\Enums\ActionSize;
use App\Models\User;
use Filament\Notifications\Actions\Action as ActionsAction;
use Filament\Tables\Actions\ActionGroup;

class AttendanceTeacherRelationManager extends RelationManager
{
    protected static string $relationship = 'memorizers';

    protected static bool $isLazy = false;

    protected static ?string $title = 'تسجيل الحضور والغياب';

    protected static ?string $icon = 'heroicon-o-user-group';


    protected function canView(Model $record): bool
    {
        return auth()->user()->isTeacher();
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->weight(FontWeight::Bold)
                    ->searchable()
                    ->icon(function (Memorizer $record) {
                        $attendance = $record->attendances()
                            ->whereDate('date', now()->toDateString())
                            ->first();

                        if ($attendance && $attendance->check_in_time) {
                            return 'heroicon-o-check-circle';
                        }

                        if ($attendance && !$attendance->check_in_time) {
                            return 'heroicon-o-x-circle';
                        }

                        return 'heroicon-o-clock';
                    })
                    ->color(function (Memorizer $record) {
                        $attendance = $record->attendances()
                            ->whereDate('date', now()->toDateString())
                            ->first();

                        if ($attendance && $attendance->check_in_time) {
                            return 'success';
                        }

                        if ($attendance && !$attendance->check_in_time) {
                            return 'danger';
                        }

                        return 'warning';
                    })
                    ->iconPosition('before')
                    ->sortable()
                    ->label('الإسم'),

                TextColumn::make('todayAttendance.score')
                    ->label('تقييم اليوم')
                    ->state(function (Memorizer $record) {
                        $attendance = $record->attendances()
                            ->whereDate('date', now()->toDateString())
                            ->first();

                        return $attendance?->score;
                    })
                    ->badge()
                    ->color(fn(string|null $state) => match ($state) {
                        'ممتاز' => 'success',
                        'حسن' => 'info',
                        'جيد' => 'warning',
                        'لا بأس به' => 'gray',
                        'لم يحفظ' => 'danger',
                        'لم يستظهر' => 'danger',
                        default => null,
                    }),

            ])
            ->actions([
                Action::make('send_whatsapp')
                    ->tooltip('إرسال رسالة واتساب')
                    ->label('')
                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->color('success')
                    ->hidden(function (Memorizer $record) {
                        return !$record->phone;
                    })
                    ->form([
                        ToggleButtons::make('message_type')
                            ->label('نوع الرسالة')
                            ->options([
                                'absence' => 'رسالة غياب',
                                'trouble' => 'رسالة شغب',
                                'no_memorization' => 'رسالة عدم الحفظ'
                            ])
                            ->colors([
                                'absence' => 'danger',
                                'trouble' => 'warning',
                                'no_memorization' => 'info'
                            ])
                            ->icons([
                                'absence' => 'heroicon-o-exclamation-circle',
                                'trouble' => 'heroicon-o-exclamation-circle',
                                'no_memorization' => 'heroicon-o-exclamation-circle'
                            ])
                            ->default(function (Memorizer $record) {
                                $attendance = $record->attendances()
                                    ->whereDate('date', now()->toDateString())
                                    ->first();

                                if (!$attendance) {
                                    return 'absence';
                                }

                                if ($attendance->notes) {
                                    return 'trouble';
                                }

                                if (
                                    $attendance->score === MemorizationScore::NOT_MEMORIZED->value ||
                                    $attendance->score === MemorizationScore::NOT_REVIEWED->value
                                ) {
                                    return 'no_memorization';
                                }

                                return 'absence';
                            })
                            ->reactive()
                            ->afterStateUpdated(function ($set, $record, $state) {
                                $set('message', $record->getMessageToSend($state));
                            })
                            ->inline()
                            ->required(),
                        Textarea::make('message')
                            ->label('نص الرسالة')
                            ->afterStateHydrated(function ($set, $record, $get) {
                                $state = $get('message_type');
                                $set('message', $record->getMessageToSend($state));
                            })
                            ->dehydrated(false)
                            ->rows(8),
                    ])
                    ->action(function (Memorizer $record, array $data) {
                        $phone = $record->phone;
                        if (!$phone) {
                            return;
                        }

                        $phone = preg_replace('/[^0-9]/', '', $phone);
                        $message = urlencode($data['message']);
                        $whatsappUrl = "https://wa.me/{$phone}?text={$message}";

                        return redirect()->away($whatsappUrl);
                    }),

                Action::make('mark_present')
                    ->tooltip('تسجيل حضور')
                    ->label('')
                    ->size('xl')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->hidden(function (Memorizer $record) {
                        $attendance = $record->attendances()
                            ->whereDate('date', now()->toDateString())
                            ->first();

                        return $attendance;
                    })
                    ->action(function (Memorizer $record) {
                        Attendance::firstOrCreate([
                            'memorizer_id' => $record->id,
                            'date' => now()->toDateString(),
                        ], [
                            'check_in_time' => now()->toTimeString(),
                        ]);

                        Notification::make()
                            ->title('تم تسجيل الحضور بنجاح')
                            ->success()
                            ->send();
                    }),

                Action::make('mark_absent')
                    ->tooltip('تسجيل غياب')
                    ->label('')
                    ->size('xl')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->hidden(function (Memorizer $record) {
                        $attendance = $record->attendances()
                            ->whereDate('date', now()->toDateString())
                            ->first();

                        return $attendance;
                    })
                    ->requiresConfirmation()
                    ->modalHeading('تأكيد تسجيل الغياب')
                    ->modalDescription('هل أنت متأكد من تسجيل الغياب لهذا الطالب؟')
                    ->modalSubmitActionLabel('تأكيد الغياب')
                    ->action(function (Memorizer $record) {
                        Attendance::updateOrCreate(
                            [
                                'memorizer_id' => $record->id,
                                'date' => now()->toDateString(),
                            ],
                            [
                                'check_in_time' => null,
                            ]
                        );

                        Notification::make()
                            ->title('تم تسجيل الغياب بنجاح')
                            ->success()
                            ->send();
                    }),

                Action::make('clear_attendance')
                    ->tooltip('إلغاء التسجيل')
                    ->label('')
                    ->icon('heroicon-o-trash')
                    ->color('gray')
                    ->hidden(function (Memorizer $record) {
                        return !$record->attendances()
                            ->whereDate('date', now()->toDateString())
                            ->exists();
                    })
                    ->requiresConfirmation()
                    ->modalHeading('تأكيد إلغاء التسجيل')
                    ->modalDescription('هل أنت متأكد من إلغاء تسجيل الحضور/الغياب لهذا الطالب؟')
                    ->modalSubmitActionLabel('تأكيد الإلغاء')
                    ->action(function (Memorizer $record) {
                        $record->attendances()
                            ->whereDate('date', now()->toDateString())
                            ->delete();

                        Notification::make()
                            ->title('تم إلغاء التسجيل بنجاح')
                            ->success()
                            ->send();
                    }),

                Action::make('add_notes')
                    ->tooltip('إضافة ملاحظات وتقييم')
                    ->label('')
                    ->icon('heroicon-o-document-text')
                    ->size(ActionSize::ExtraLarge)
                    ->color('info')
                    ->slideOver()
                    ->modalSubmitActionLabel('حفظ')
                    ->form([
                        Section::make('تقييم الحفظ')
                            ->schema([
                                ToggleButtons::make('score')
                                    ->label('تقييم اليوم')
                                    ->columnSpanFull()
                                    ->inline()

                                    ->options(MemorizationScore::class)
                                    ->required()
                                    ->default(function (Memorizer $record) {
                                        $attendance = $record->attendances()
                                            ->whereDate('date', now()->toDateString())
                                            ->first();


                                        return $attendance?->score ?? MemorizationScore::GOOD;
                                    }),
                            ]),

                        Section::make('ملاحظات السلوك')
                            ->description('حدد السلوكيات التي ظهرت اليوم')
                            ->compact()
                            ->collapsed()
                            ->collapsible()
                            ->schema([
                                ToggleButtons::make('behavioral_issues')
                                    ->label('')
                                    ->options(Troubles::class)
                                    ->inline()
                                    ->multiple()
                                    ->columnSpanFull()
                                    ->default(function (Memorizer $record) {
                                        $attendance = $record->attendances()
                                            ->whereDate('date', now()->toDateString())
                                            ->first();

                                        if ($attendance && $attendance->notes != null) {
                                            return $attendance->notes;
                                        }

                                        return [];
                                    }),

                                Textarea::make('custom_note')
                                    ->label('ملاحظات إضافية')
                                    ->placeholder('أضف أي ملاحظات إضافية هنا...')
                                    ->default(function (Memorizer $record) {
                                        $attendance = $record->attendances()
                                            ->whereDate('date', now()->toDateString())
                                            ->first();

                                        if ($attendance && $attendance->custom_note) {
                                            return $attendance->custom_note;
                                        }

                                        return '';
                                    })
                                    ->rows(3),
                            ]),
                    ])
                    ->visible(fn(Memorizer $record) => $record->attendances()
                        ->whereDate('date', now()->toDateString())
                        ->where('check_in_time', '!=', null)
                        ->exists())
                    ->action(function (Memorizer $record, array $data) {
                        $attendance = $record->attendances()
                            ->whereDate('date', now()->toDateString())
                            ->first();

                        if ($attendance) {
                            $notes =  $data['behavioral_issues'];

                            $attendance->update([
                                'notes' => $notes,
                                'score' => $data['score'],
                                'custom_note' => $data['custom_note'],
                            ]);


                            if ($data['behavioral_issues'] != null) {
                                $associationAdmins = User::where('email', 'LIKE', '%@association.com')->get();
                                $troublesLabels = '';
                                foreach ($data['behavioral_issues'] as $trouble) {
                                    $troublesLabels .= Troubles::tryFrom($trouble)->getLabel() . ', ';
                                }
                                Notification::make()
                                    ->title("مشكلة سلوكية للطالب {$record->name}")
                                    ->body("قام الطالب {$record->name} في مجموعة {$this->ownerRecord->name} بـ " . $troublesLabels . " بتاريخ " . now()->format('Y-m-d'))
                                    ->warning()
                                    ->actions([
                                        ActionsAction::make('view_attendance')
                                            ->label('عرض الحضور')
                                            ->url(fn() => GroupResource::getUrl('view', ['record' => $this->ownerRecord, 'activeRelationManager' => '0'], panel: 'association'))
                                    ])
                                    ->sendToDatabase($associationAdmins);
                            }

                            Notification::make()
                                ->title('تم حفظ الملاحظات بنجاح')
                                ->success()
                                ->send();
                        }
                    }),
                Action::make('edit_student')
                    ->tooltip('تعديل معلومات الطالب')
                    ->label('')
                    ->icon('heroicon-o-pencil-square')
                    ->color('info')
                    ->form([
                        \Filament\Forms\Components\TextInput::make('name')
                            ->label('الإسم')
                            ->required(),
                        \Filament\Forms\Components\DatePicker::make('birth_date')
                            ->label('تاريخ الميلاد')
                            ->required(),
                        \Filament\Forms\Components\TextInput::make('phone')
                            ->label('رقم الهاتف')
                            ->tel(),
                    ])
                    ->fillForm(fn(Memorizer $record): array => [
                        'name' => $record->name,
                        'birth_date' => $record->birth_date,
                        'phone' => $record->phone,
                    ])
                    ->action(function (Memorizer $record, array $data): void {
                        $record->update([
                            'name' => $data['name'],
                            'birth_date' => $data['birth_date'],
                            'phone' => $data['phone'],
                        ]);

                        Notification::make()
                            ->title('تم تحديث معلومات الطالب بنجاح')
                            ->success()
                            ->send();
                    })

            ], ActionsPosition::BeforeColumns)
            ->headerActions([
                Action::make('export_table')
                    ->label('تصدير كصورة')
                    ->icon('heroicon-o-share')
                    ->size(ActionSize::Small)
                    ->color('success')
                    ->action(function () {
                        $date = now()->format('Y-m-d');

                        $memorizers = $this->ownerRecord->memorizers()
                            ->with(['attendances' => function ($query) use ($date) {
                                $query->whereDate('date', $date);
                            }])
                            ->get();

                        $html = view('components.attendance-export-table', [
                            'memorizers' => $memorizers,
                            'group' => $this->ownerRecord,
                            'date' => $date,
                        ])->render();

                        $this->dispatch('export-table', [
                            'html' => $html,
                            'groupName' => $this->ownerRecord->name
                        ]);
                    })
            ])
            ->bulkActions([
                BulkAction::make('mark_attendance_bulk')
                    ->label('تسجيل الحضور للمحددين')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->action(function ($livewire) {
                        $records = $livewire->getSelectedTableRecords();
                        $records = Memorizer::find($records);
                        $records->each(function (Memorizer $memorizer) {
                            Attendance::firstOrCreate([
                                'memorizer_id' => $memorizer->id,
                                'date' => now()->toDateString(),
                            ], [
                                'check_in_time' => now()->toTimeString(),
                            ]);
                        });

                        Notification::make()
                            ->title('تم تسجيل الحضور بنجاح للطلاب المحددين')
                            ->success()
                            ->send();
                    }),

                BulkAction::make('mark_absence_bulk')
                    ->label('تسجيل الغياب للمحددين')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('تأكيد تسجيل الغياب الجماعي')
                    ->modalDescription('هل أنت متأكد من تسجيل الغياب للطلاب المحددين؟')
                    ->modalSubmitActionLabel('تأكيد الغياب للجميع')
                    ->action(function ($livewire) {
                        $records = $livewire->getSelectedTableRecords();
                        $records = Memorizer::find($records);
                        $records->each(function (Memorizer $memorizer) {
                            Attendance::updateOrCreate(
                                [
                                    'memorizer_id' => $memorizer->id,
                                    'date' => now()->toDateString(),
                                ],
                                [
                                    'check_in_time' => null,
                                ]
                            );
                        });

                        Notification::make()
                            ->title('تم تسجيل الغياب بنجاح للطلاب المحددين')
                            ->success()
                            ->send();
                    }),

                BulkAction::make('send_whatsapp_bulk')
                    ->label('إرسال رسائل واتساب للمحددين')
                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->color('success')
                    ->hidden()
                    ->form([
                        Textarea::make('message')
                            ->label('نص الرسالة')
                            ->default(
                                "السلام عليكم ورحمة الله وبركاته\n" .
                                    "نود إعلامكم أن [الطالب/الطالبة] [اسم الطالب] [لم يحضر/لم تحضر] اليوم إلى حلقة التحفيظ.\n" .
                                    "نرجوا إخبارنا في حال وجود أي ظرف.\n\n" .
                                    "جزاكم الله خيراً"
                            )
                            ->required()
                            ->rows(5),
                    ])
                    ->action(function ($records, array $data) {
                        $records = Memorizer::find($records);
                        $urls = [];

                        foreach ($records as $record) {
                            $phone = $record->phone ?? $record->guardian?->phone;
                            if (!$phone) {
                                continue;
                            }

                            $phone = preg_replace('/[^0-9]/', '', $phone);
                            $personalizedMessage = $data['message'];

                            // Replace gender-specific placeholders
                            $personalizedMessage = str_replace(
                                ['[الطالب/الطالبة]', '[لم يحضر/لم تحضر]'],
                                [
                                    $record->sex === 'male' ? 'الطالب' : 'الطالبة',
                                    $record->sex === 'male' ? 'لم يحضر' : 'لم تحضر'
                                ],
                                $personalizedMessage
                            );

                            // Replace name placeholder
                            $personalizedMessage = str_replace('[اسم الطالب]', $record->name, $personalizedMessage);

                            $message = urlencode($personalizedMessage);
                            $urls[] = "https://wa.me/{$phone}?text={$message}";
                        }

                        return response()->json([
                            'urls' => $urls,
                            'script' => "urls.forEach(url => window.open(url, '_blank'));"
                        ]);
                    }),
            ])
            ->paginated(false);
    }
}
