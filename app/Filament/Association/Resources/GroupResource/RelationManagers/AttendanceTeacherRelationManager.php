<?php

namespace App\Filament\Association\Resources\GroupResource\RelationManagers;

use App\Enums\MemorizationScore;
use App\Enums\Troubles;
use App\Filament\Association\Resources\GroupResource;
use App\Models\Attendance;
use App\Models\Memorizer;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\Size;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AttendanceTeacherRelationManager extends RelationManager
{
    protected static string $relationship = 'memorizers';

    protected static bool $isLazy = false;

    protected static ?string $title = 'تسجيل الحضور والغياب';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-user-group';

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

                        if ($attendance && ! $attendance->check_in_time) {
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

                        if ($attendance && ! $attendance->check_in_time) {
                            return 'danger';
                        }

                        return '';
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
                    ->badge(),

            ])
            ->recordActions([
                self::sendNotificationAction(),
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
                    ->schema([
                        Toggle::make('send_message')
                            ->label('إرسال الرسالة الي الولي ؟')
                            ->helperText('سيتم تحويلك تلقائياً لإرسال رسالة غياب للولي. هذا مهم للتوثيق وحمايتك في حال حدوث أي مشكلة , نسأل الله السلامة.')
                            ->default(true),
                    ])
                    ->requiresConfirmation()
                    ->modalDescription('')
                    ->modalHeading('تأكيد تسجيل الغياب وإرسال الرسالة الي الولي')
                    ->modalSubmitActionLabel('تأكيد والإرسال')
                    ->action(function (Memorizer $record, array $data) {
                        // Create or update attendance record
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

                        // Only proceed with WhatsApp message if enabled
                        if (! $data['send_message']) {
                            return;
                        }

                        // Get phone number, checking both student and guardian
                        $phone = $record->phone ?? $record->guardian?->phone;
                        if (! $phone) {
                            return;
                        }

                        // Clean phone number and prepare message
                        $phone = preg_replace('/[^0-9]/', '', $phone);
                        $originalMessage = $record->getMessageToSend('absence');
                        $message = urlencode($originalMessage);

                        // Use route helper for consistent URL generation
                        $whatsappUrl = "https://wa.me/{$phone}?text={$message}";

                        $record->reminderLogs()->create([
                            'type' => 'absence',
                            'phone_number' => $record->phone,
                            'is_parent' => true,
                            'message' => Str::of($originalMessage)->limit(50),
                        ]);

                        return redirect()->away($whatsappUrl);
                    }),

                Action::make('clear_attendance')
                    ->tooltip('إلغاء التسجيل')
                    ->label('')
                    ->icon('heroicon-o-trash')
                    ->color('gray')
                    ->hidden(function (Memorizer $record) {
                        return ! $record->attendances()
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
                    ->size(Size::ExtraLarge)
                    ->color('info')
                    ->slideOver()
                    ->modalSubmitActionLabel('حفظ')
                    ->schema([
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
                    ->visible(fn (Memorizer $record) => $record->attendances()
                        ->whereDate('date', now()->toDateString())
                        ->where('check_in_time', '!=', null)
                        ->exists())
                    ->action(function (Memorizer $record, array $data) {
                        $attendance = $record->attendances()
                            ->whereDate('date', now()->toDateString())
                            ->first();

                        if ($attendance) {
                            $notes = $data['behavioral_issues'];

                            $attendance->update([
                                'notes' => $notes,
                                'score' => $data['score'],
                                'custom_note' => $data['custom_note'],
                            ]);

                            if ($data['behavioral_issues'] != null) {
                                $associationAdmins = User::where('email', 'LIKE', '%@association.com')->get();
                                $troublesLabels = '';
                                foreach ($data['behavioral_issues'] as $trouble) {
                                    $troublesLabels .= Troubles::tryFrom($trouble)->getLabel().', ';
                                }
                                Notification::make()
                                    ->title("مشكلة سلوكية للطالب {$record->name}")
                                    ->body("قام الطالب {$record->name} في مجموعة {$this->ownerRecord->name} بـ ".$troublesLabels.' بتاريخ '.now()->format('Y-m-d'))
                                    ->warning()
                                    ->actions([
                                        Action::make('view_attendance')
                                            ->label('عرض الحضور')
                                            ->url(fn () => GroupResource::getUrl('view', ['record' => $this->ownerRecord, 'activeRelationManager' => '0'], panel: 'association')),
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
                    ->schema([
                        DatePicker::make('birth_date')
                            ->label('تاريخ الميلاد')
                            ->required(),

                    ])
                    ->fillForm(fn (Memorizer $record): array => [
                        'birth_date' => $record->birth_date,
                    ])
                    ->action(function (Memorizer $record, array $data): void {
                        $record->update([
                            'birth_date' => $data['birth_date'],
                        ]);

                        Notification::make()
                            ->title('تم تحديث معلومات الطالب بنجاح')
                            ->success()
                            ->send();
                    }),

            ], RecordActionsPosition::BeforeColumns)
            ->headerActions([
                Action::make('export_table')
                    ->label('إرسال التقرير اليومي')
                    ->icon('heroicon-o-share')
                    ->size(Size::Small)
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
                            'groupName' => $this->ownerRecord->name,
                        ]);
                    }),
            ])
            ->toolbarActions([
                BulkAction::make('mark_attendance_bulk')
                    ->label('تسجيل الحضور')
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
                    ->label('تسجيل الغياب')
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
                    ->label('إرسال رسائل واتساب')
                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->color('success')
                    ->hidden()
                    ->form([
                        Textarea::make('message')
                            ->label('نص الرسالة')
                            ->default(
                                "السلام عليكم ورحمة الله وبركاته\n".
                                    "نود إعلامكم أن [الطالب/الطالبة] [اسم الطالب] [لم يحضر/لم تحضر] اليوم إلى حلقة التحفيظ.\n".
                                    "نرجوا إخبارنا في حال وجود أي ظرف.\n\n".
                                    'جزاكم الله خيراً'
                            )
                            ->required()
                            ->rows(5),
                    ])
                    ->action(function ($records, array $data) {
                        $records = Memorizer::find($records);
                        $urls = [];

                        foreach ($records as $record) {
                            $phone = $record->phone ?? $record->guardian?->phone;
                            if (! $phone) {
                                continue;
                            }

                            $phone = preg_replace('/[^0-9]/', '', $phone);
                            $personalizedMessage = $data['message'];

                            // Replace gender-specific placeholders
                            $personalizedMessage = str_replace(
                                ['[الطالب/الطالبة]', '[لم يحضر/لم تحضر]'],
                                [
                                    $record->sex === 'male' ? 'الطالب' : 'الطالبة',
                                    $record->sex === 'male' ? 'لم يحضر' : 'لم تحضر',
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
                            'script' => "urls.forEach(url => window.open(url, '_blank'));",
                        ]);
                    }),
            ])
            ->paginated(false);
    }

    public static function sendNotificationAction(): Action
    {
        return Action::make('send_whatsapp')
            ->tooltip('إرسال رسالة واتساب')
            ->label('')
            ->icon('tabler-message-circle')
            ->color('success')
            ->hidden(function (Memorizer $record) {
                return ! $record->phone;
            })
            ->schema([
                ToggleButtons::make('message_type')
                    ->label('نوع الرسالة')
                    ->options([
                        'absence' => 'رسالة غياب',
                        'trouble' => 'رسالة شغب',
                        'no_memorization' => 'رسالة عدم الحفظ',
                        'late' => 'رسالة تأخر',
                    ])
                    ->colors([
                        'absence' => 'danger',
                        'trouble' => 'warning',
                        'no_memorization' => 'info',
                        'late' => Color::Orange,
                    ])
                    ->icons([
                        'absence' => 'heroicon-o-x-circle',
                        'trouble' => 'heroicon-o-exclamation-circle',
                        'no_memorization' => 'heroicon-o-exclamation-circle',
                        'late' => 'heroicon-o-clock',
                    ])
                    ->default(function (Memorizer $record) {
                        $attendance = $record->attendances()
                            ->whereDate('date', now()->toDateString())
                            ->first();

                        if (! $attendance) {
                            return 'absence';
                        }

                        if ($attendance->notes) {
                            // Check if any of the notes indicate lateness
                            $notes = is_array($attendance->notes) ? $attendance->notes : [];
                            if (in_array(Troubles::TARDY->value, $notes)) {
                                return 'late';
                            }

                            return 'trouble';
                        }

                        if (
                            $attendance->score === MemorizationScore::NOT_MEMORIZED->value
                        ) {
                            return 'no_memorization';
                        }

                        if ($attendance->check_in_time) {
                            return 'late';
                        }

                        return 'absence';
                    })
                    ->reactive()
                    ->afterStateUpdated(function ($set, Memorizer $record, $state) {
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
                    ->rows(8),
            ])
            ->action(function (Memorizer $record, array $data) {
                $phone = $record->phone;
                if (! $phone) {
                    return;
                }
                $phone = preg_replace('/[^0-9]/', '', $phone);
                $originalMessage = $data['message'];
                $message = urlencode($originalMessage);
                $whatsappUrl = route('memorizer-'.$data['message_type'].'-whatsapp', [$phone, $message, $record->id]);

                return redirect($whatsappUrl);
            });
    }
}
