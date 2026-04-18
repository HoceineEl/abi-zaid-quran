<?php

namespace App\Filament\Actions\Attendance;

use App\Models\Memorizer;
use App\Services\AttendanceActionService;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\ActionSize;
use Filament\Tables\Actions\BulkAction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\HtmlString;

class BulkSendWhatsAppAction extends BulkAction
{
    public static function getDefaultName(): ?string
    {
        return 'send_whatsapp_bulk';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('رسالة واتساب')
            ->icon('tabler-message-circle')
            ->color('success')
            ->size(ActionSize::ExtraSmall)
            ->modalHeading('إرسال رسالة واتساب جماعية')
            ->modalDescription('سيتم توليد رسالة مخصصة لكل طالب (بناءً على اسمه وتاريخ اليوم)، وفتح محادثة واتساب لكل واحد منهم في نافذة جديدة. الطلاب بدون رقم هاتف سيتم تخطيهم.')
            ->modalSubmitActionLabel('فتح محادثات واتساب')
            ->deselectRecordsAfterCompletion()
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
                    ->default('absence')
                    ->inline()
                    ->required(),

                Placeholder::make('notice')
                    ->label('')
                    ->content(new HtmlString(
                        '<div class="text-sm text-gray-600 dark:text-gray-400">'
                        . 'قد يمنع المتصفح فتح عدة نوافذ. اسمح بالنوافذ المنبثقة عند الطلب لضمان فتح جميع المحادثات.'
                        . '</div>'
                    )),
            ])
            ->action(function (Collection $records, array $data, $livewire): void {
                $messageType = $data['message_type'];

                $records->loadMissing('guardian');

                $urls = [];
                $skipped = 0;

                foreach ($records as $memorizer) {
                    /** @var Memorizer $memorizer */
                    $dispatch = AttendanceActionService::buildWhatsAppDispatch($memorizer, $messageType);

                    if (! $dispatch) {
                        $skipped++;
                        continue;
                    }

                    AttendanceActionService::logWhatsAppReminder($memorizer, $messageType, $dispatch);
                    $urls[] = $dispatch['url'];
                }

                if (empty($urls)) {
                    Notification::make()
                        ->title('لم يتم إرسال أي رسالة')
                        ->body('لا يملك أي من الطلاب المحددين رقم هاتف.')
                        ->warning()
                        ->send();

                    return;
                }

                $livewire->js(sprintf(
                    "const urls = %s; urls.forEach((u, i) => setTimeout(() => window.open(u, '_blank'), i * 150));",
                    json_encode($urls, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                ));

                $title = "جاري فتح " . count($urls) . " محادثة واتساب";
                if ($skipped > 0) {
                    $title .= " (تم تخطي {$skipped} طالب بدون رقم هاتف)";
                }

                Notification::make()
                    ->title($title)
                    ->success()
                    ->send();
            });
    }
}
