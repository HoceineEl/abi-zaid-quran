<?php

namespace App\Filament\Actions\Attendance;

use App\Enums\MemorizationScore;
use App\Enums\Troubles;
use App\Models\Memorizer;
use App\Services\AttendanceActionService;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\ToggleButtons;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\ActionSize;
use Filament\Tables\Actions\Action;

class SendWhatsAppAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'send_whatsapp';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->tooltip('إرسال رسالة واتساب')
            ->label('')
            ->size(ActionSize::ExtraLarge)
            ->icon('tabler-message-circle')
            ->color('success')
            ->hidden(fn (Memorizer $record) => ! AttendanceActionService::resolvePhone($record))
            ->form([
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
                    ->default(fn (Memorizer $record) => self::resolveDefaultMessageType($record))
                    ->reactive()
                    ->afterStateUpdated(function ($set, Memorizer $record, $state): void {
                        $set('message', $record->getMessageToSend($state));
                    })
                    ->inline()
                    ->required(),

                Textarea::make('message')
                    ->label('نص الرسالة')
                    ->afterStateHydrated(function ($set, $record, $get): void {
                        $state = $get('message_type');
                        $set('message', $record->getMessageToSend($state));
                    })
                    ->rows(8),
            ])
            ->action(function (Memorizer $record, array $data) {
                $dispatch = AttendanceActionService::buildWhatsAppDispatch(
                    $record,
                    $data['message_type'],
                    $data['message'],
                );

                if (! $dispatch) {
                    return;
                }

                AttendanceActionService::logWhatsAppReminder($record, $data['message_type'], $dispatch);

                return redirect()->away($dispatch['url']);
            });
    }

    /**
     * Determine the default message type based on today's attendance.
     */
    private static function resolveDefaultMessageType(Memorizer $record): string
    {
        $attendance = $record->attendances()
            ->whereDate('date', now()->toDateString())
            ->first();

        if (! $attendance) {
            return 'absence';
        }

        if ($attendance->notes) {
            $notes = is_array($attendance->notes) ? $attendance->notes : [];
            if (in_array(Troubles::TARDY->value, $notes)) {
                return 'late';
            }

            return 'trouble';
        }

        if ($attendance->score === MemorizationScore::NOT_MEMORIZED) {
            return 'no_memorization';
        }

        if ($attendance->check_in_time) {
            return 'late';
        }

        return 'absence';
    }
}
