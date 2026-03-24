<?php

namespace App\Filament\Actions\Attendance;

use App\Enums\AttendanceStatus;
use App\Models\Memorizer;
use Filament\Notifications\Notification;
use Filament\Support\Enums\ActionSize;
use Filament\Support\Enums\IconSize;
use Filament\Tables\Actions\Action;

class JustifyAbsenceAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'justify_absence';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->tooltip('تبرير غياب اليوم')
            ->label('')
            ->icon(AttendanceStatus::ABSENT_JUSTIFIED->getIcon())
            ->color(AttendanceStatus::ABSENT_JUSTIFIED->getColor())
            ->size(ActionSize::ExtraLarge)
            ->iconSize(IconSize::Large)
            ->requiresConfirmation()
            ->modalHeading('تبرير الغياب')
            ->modalDescription('هل أنت متأكد من تبرير غياب هذا الطالب اليوم؟')
            ->modalSubmitActionLabel('تأكيد التبرير')
            ->visible(function (Memorizer $record): bool {
                $attendance = $record->attendances()
                    ->whereDate('date', now()->toDateString())
                    ->first();

                return $attendance
                    && $attendance->isAbsent()
                    && ! $attendance->absence_justified;
            })
            ->action(function (Memorizer $record): void {
                $record->attendances()
                    ->whereDate('date', now()->toDateString())
                    ->update(['absence_justified' => true]);

                Notification::make()
                    ->title('تم تبرير الغياب بنجاح')
                    ->success()
                    ->send();
            });
    }
}
