<?php

namespace App\Filament\Actions\Attendance;

use Filament\Actions\Action;
use Filament\Support\Enums\Size;
use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Memorizer;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Support\Enums\IconSize;

class MarkAbsentAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'mark_absent';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->tooltip('تسجيل غياب')
            ->label('')
            ->size(Size::ExtraLarge)
            ->iconSize(IconSize::Large)
            ->extraAttributes(['class' => '[&_svg]:w-8 [&_svg]:h-8'])
            ->icon(AttendanceStatus::ABSENT_UNJUSTIFIED->getIcon())
            ->color(AttendanceStatus::ABSENT_UNJUSTIFIED->getColor())
            ->hidden(fn (Memorizer $record) => $record->attendances()
                ->whereDate('date', now()->toDateString())
                ->exists())
            ->form([
                Toggle::make('absence_justified')
                    ->label('غياب مبرر؟')
                    ->helperText('فعّل هذا الخيار إذا كان الغياب بعذر مقبول (مرض، سفر، ...)')
                    ->default(false)
                    ->onIcon('heroicon-m-shield-check')
                    ->offIcon('heroicon-m-x-circle')
                    ->onColor(AttendanceStatus::ABSENT_JUSTIFIED->getColor())
                    ->offColor(AttendanceStatus::ABSENT_UNJUSTIFIED->getColor()),

                Toggle::make('send_message')
                    ->label('إرسال رسالة للولي؟')
                    ->helperText('سيتم تحويلك تلقائياً لإرسال رسالة غياب للولي. هذا مهم للتوثيق وحمايتك في حال حدوث أي مشكلة.')
                    ->default(true),
            ])
            ->requiresConfirmation()
            ->modalDescription('')
            ->modalHeading('تأكيد تسجيل الغياب')
            ->modalSubmitActionLabel('تأكيد')
            ->action(function (Memorizer $record, array $data) {
                Attendance::updateOrCreate(
                    [
                        'memorizer_id' => $record->id,
                        'date' => now()->toDateString(),
                    ],
                    [
                        'check_in_time' => null,
                        'absence_justified' => $data['absence_justified'] ?? false,
                    ]
                );

                Notification::make()
                    ->title(($data['absence_justified'] ?? false)
                        ? 'تم تسجيل الغياب المبرر بنجاح'
                        : 'تم تسجيل الغياب بنجاح')
                    ->success()
                    ->send();

                if (! ($data['send_message'] ?? false)) {
                    return;
                }

                $phone = $record->phone ?? $record->guardian?->phone;
                if (! $phone) {
                    return;
                }

                return redirect(route('memorizer-absence-whatsapp', $record->id));
            });
    }
}
