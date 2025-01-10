<?php

namespace App\Filament\Association\Resources\GroupResource\RelationManagers;

use App\Enums\MemorizationScore;
use App\Filament\Association\Resources\MemorizerResource;
use App\Models\Attendance;
use App\Models\Memorizer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Form;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Enums\ActionsPosition;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Mpdf\Mpdf;

class MemorizersRelationManager extends RelationManager
{
    protected static string $relationship = 'memorizers';

    protected static bool $isLazy = false;

    protected static ?string $title = 'الطلبة';

    protected static ?string $navigationLabel = 'الطلبة';

    protected static ?string $modelLabel = 'طالب';

    protected static ?string $pluralModelLabel = 'الطلبة';

    protected static ?string $icon = 'heroicon-o-user-group';


    public function form(Form $form): Form
    {
        return MemorizerResource::form($form);
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
                    ->weight(FontWeight::Bold)
                    ->icon(function (Memorizer $record) {
                        if ($record->has_payment_this_month) {
                            return 'heroicon-o-check-circle';
                        }
                        if ($record->has_reminder_this_month) {
                            return 'heroicon-o-exclamation-circle';
                        }
                        if (!$record->has_payment_this_month) {
                            return 'heroicon-o-x-circle';
                        }

                        return null;
                    })
                    ->action(self::getPayAction())
                    ->searchable()
                    ->color(function (Memorizer $record) {
                        if ($record->has_payment_this_month) {
                            return 'success';
                        }
                        if ($record->has_reminder_this_month) {
                            return Color::Yellow;
                        }

                        return Color::Rose;
                    })
                    ->toggleable()
                    ->sortable()
                    ->label('الإسم'),
                IconColumn::make('exempt')
                    ->label('معفي')
                    ->boolean(),
                TextColumn::make('phone')
                    ->label('رقم الهاتف')
                    ->copyable()
                    ->copyMessage('تم نسخ رقم الهاتف')
                    ->copyMessageDuration(1500)
                    ->extraCellAttributes(['dir' => 'ltr'])
                    ->alignRight()
                    ->getStateUsing(function ($record) {
                        if ($record->phone) {
                            return $record->phone;
                        }
                        return $record->guardian?->phone;
                    }),

            ])
            ->filters([
                Filter::make('troublemakers')
                    ->label('الأكثر مشاكل')
                    ->form([
                        TextInput::make('trouble_threshold')
                            ->label('عدد المشاكل على الأقل')
                            ->numeric(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['trouble_threshold'],
                                fn(Builder $query, $threshold): Builder => $query->whereHas('hasTroubles', function (Builder $query) use ($threshold) {
                                    $query->select(DB::raw('COUNT(*) as trouble_count'))
                                        ->having('trouble_count', '>=', $threshold);
                                }, '>=', 1)
                            );
                    })
                    ->indicator('الأكثر مشاكل'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                ActionGroup::make([
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make(),
                    Tables\Actions\ViewAction::make(),
                    self::getTroublesAction(),
                    Action::make('send_whatsapp')
                        ->label('إرسال رسالة واتساب')
                        ->icon('tabler-brand-whatsapp')
                        ->color('success')
                        ->hidden(function (Memorizer $record) {
                            return !$record->phone && !$record->guardian?->phone;
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
                                ->rows(8),
                        ])
                        ->action(function (Memorizer $record, array $data) {
                            $phone = $record->phone ?? $record->guardian?->phone;
                            if (!$phone) {
                                return;
                            }

                            $phone = preg_replace('/[^0-9]/', '', $phone);
                            $message = urlencode($data['message']);
                            $whatsappUrl = "https://wa.me/{$phone}?text={$message}";

                            return redirect()->away($whatsappUrl);
                        }),
                ]),

                Action::make('send_payment_reminders')
                    ->tooltip('إرسال تذكير بالدفع')
                    ->iconButton()
                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->hidden(function (Memorizer $record) {
                        // Skip if no phone number available
                        if (!$record->phone && !$record->guardian?->phone) {
                            return true;
                        }

                        // Skip if student has already paid this month
                        if ($record->has_payment_this_month) {
                            return true;
                        }
                    })
                    ->url(function (Memorizer $record) {

                        return MemorizerResource::getWhatsAppUrl($record);
                    }, true),

            ], ActionsPosition::BeforeColumns)
            ->bulkActions([
                BulkAction::make('pay_monthly_fee_bulk')
                    ->label('تسديد الرسوم للمحددين')
                    ->requiresConfirmation()
                    ->color('indigo')
                    ->icon('heroicon-o-currency-dollar')
                    ->modalDescription('هل أنت متأكد من تسجيل دفع الرسوم الشهرية للطلاب المحددين؟')
                    ->modalHeading('تأكيد تسديد الرسوم الجماعي')
                    ->modalSubmitActionLabel('تأكيد الدفع للجميع')
                    ->action(function ($livewire) {
                        $records = $livewire->getSelectedTableRecords();
                        $records = Memorizer::find($records);
                        $records->each(function (Memorizer $memorizer) {
                            if (! $memorizer->has_payment_this_month) {
                                $memorizer->payments()->create([
                                    'amount' => $memorizer->exempt ? 0 : $this->ownerRecord->price,
                                    'payment_date' => now(),
                                ]);
                            }
                        });

                        Notification::make()
                            ->title('تم الدفع بنجاح')
                            ->success()
                            ->send();
                    }),
                // BulkAction::make('mark_attendance_bulk')
                //     ->label('تسجيل الحضور للمحددين')
                //     ->icon('heroicon-o-check-circle')
                //     ->color('success')
                //     ->action(function ($livewire) {
                //         $records = $livewire->getSelectedTableRecords();
                //         $records = Memorizer::find($records);
                //         $records->each(function (Memorizer $memorizer) {
                //             Attendance::firstOrCreate([
                //                 'memorizer_id' => $memorizer->id,
                //                 'date' => now()->toDateString(),
                //             ], [
                //                 'check_in_time' => now()->toTimeString(),
                //             ]);
                //         });

                //         Notification::make()
                //             ->title('تم تسجيل الحضور بنجاح للطلاب المحددين')
                //             ->success()
                //             ->send();
                //     }),
                // BulkAction::make('mark_absence_bulk')
                //     ->label('تسجيل الغياب للمحددين')
                //     ->icon('heroicon-o-x-circle')
                //     ->color('danger')
                //     ->requiresConfirmation()
                //     ->modalHeading('تأكيد تسجيل الغياب الجماعي')
                //     ->modalDescription('هل أنت متأكد من تسجيل الغياب للطلاب المحددين؟')
                //     ->modalSubmitActionLabel('تأكيد الغياب للجميع')
                //     ->action(function ($livewire) {
                //         $records = $livewire->getSelectedTableRecords();
                //         $records = Memorizer::find($records);
                //         $records->each(function (Memorizer $memorizer) {
                //             Attendance::updateOrCreate(
                //                 [
                //                     'memorizer_id' => $memorizer->id,
                //                     'date' => now()->toDateString(),
                //                 ],
                //                 [
                //                     'check_in_time' => null,
                //                 ]
                //             );
                //         });

                //         Notification::make()
                //             ->title('تم تسجيل الغياب بنجاح للطلاب المحددين')
                //             ->success()
                //             ->send();
                //     }),

                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])->paginated(false);
    }

    public static function getPayAction(): Action
    {
        return  Action::make('pay_this_month')
            ->label('دفع')
            ->icon('heroicon-o-currency-dollar')
            ->color('success')
            ->requiresConfirmation()
            ->hidden(fn(Memorizer $record) => $record->has_payment_this_month)
            ->modalDescription('هل تريد تسجيل دفعة جديدة لهذا الشهر؟')
            ->modalHeading('تسجيل دفعة جديدة')
            ->form(function (Memorizer $record) {
                return [
                    TextInput::make('amount')
                        ->label('المبلغ')
                        ->helperText('المبلغ المستحق للشهر')
                        ->numeric()
                        ->default(fn() => $record->group->price ?? 70),
                ];
            })
            ->action(function (Memorizer $record, array $data) {
                $record->payments()->create([
                    'amount' => $data['amount'],
                    'payment_date' => now(),
                ]);

                Notification::make()
                    ->title('تم تسجيل الدفعة بنجاح')
                    ->success()
                    ->send();
            });
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                TextEntry::make('name')
                    ->label('الاسم'),
                TextEntry::make('attendances')
                    ->label('المشاكل')
                    ->getStateUsing(function (Memorizer $record) {
                        $troubles = [];
                        foreach ($record->attendances as $attendance) {
                            if ($attendance->notes) {
                                foreach ($attendance->notes as $note) {
                                    $troubles[] = \App\Enums\Troubles::tryFrom($note)?->getLabel();
                                }
                            }
                        }
                        return implode(', ', $troubles);
                    })
            ]);
    }

    public static function getTroublesAction(): Action
    {
        return Action::make('view_troubles')
            ->label('عرض المشاكل')
            ->icon('heroicon-o-exclamation-triangle')
            ->color('warning')
            ->modalHeading('قائمة المشاكل')
            ->modalSubmitAction(false)
            ->modalCancelAction(false)
            ->infolist(fn(Memorizer $record) => self::getTroublesInfolist($record));
    }
    public static function getTroublesInfolist(Memorizer $record): Infolist
    {
        return Infolist::make()
            ->record($record)
            ->schema([
                RepeatableEntry::make('attendancesWithTroubles')
                    ->label('قائمة المشاكل')
                    ->schema([
                        TextEntry::make('date')
                            ->label('التاريخ')
                            ->formatStateUsing(fn($state) => $state->format('Y-m-d')),
                        TextEntry::make('notes')
                            ->label('المشاكل')
                            ->badge()
                            ->hidden(fn($state) => empty($state))
                            ->getStateUsing(fn($record) => $record->notes ? array_map(fn($note) => \App\Enums\Troubles::tryFrom($note)?->getLabel(), $record->notes) : []),
                        TextEntry::make('score')
                            ->label('التقييم')
                            ->badge()
                            ->hidden(fn($state) => empty($state))
                            ->getStateUsing(fn($record) => $record->score),
                        TextEntry::make('custom_note')
                            ->label('التعليق الخاص')
                            ->badge()
                            ->hidden(fn($state) => empty($state))
                            ->getStateUsing(fn($record) => $record->custom_note),
                    ])
                    ->grid(2)
            ]);
    }
}
