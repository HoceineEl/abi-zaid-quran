<?php

namespace App\Filament\Actions\Attendance;

use Filament\Actions\Action;
use Filament\Support\Enums\Size;
use App\Models\Memorizer;
use Filament\Notifications\Notification;
use Filament\Support\Enums\IconSize;

class ClearAttendanceAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'clear_attendance';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->tooltip('إلغاء التسجيل')
            ->label('')
            ->size(Size::ExtraLarge)
            ->iconSize(IconSize::Large)
            ->extraAttributes(['class' => '[&_svg]:w-8 [&_svg]:h-8'])
            ->icon('heroicon-o-trash')
            ->color('gray')
            ->hidden(fn (Memorizer $record) => ! $record->attendances()
                ->whereDate('date', now()->toDateString())
                ->exists())
            ->requiresConfirmation()
            ->modalHeading('تأكيد إلغاء التسجيل')
            ->modalDescription('هل أنت متأكد من إلغاء تسجيل الحضور/الغياب لهذا الطالب؟')
            ->modalSubmitActionLabel('تأكيد الإلغاء')
            ->action(function (Memorizer $record): void {
                $record->attendances()
                    ->whereDate('date', now()->toDateString())
                    ->delete();

                Notification::make()
                    ->title('تم إلغاء التسجيل بنجاح')
                    ->success()
                    ->send();
            });
    }
}
