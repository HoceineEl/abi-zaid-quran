<?php

namespace App\Filament\Actions\Attendance;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Memorizer;
use Filament\Notifications\Notification;
use Filament\Support\Enums\ActionSize;
use Filament\Support\Enums\IconSize;
use Filament\Tables\Actions\Action;

class MarkPresentAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'mark_present';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->tooltip('تسجيل حضور')
            ->label('')
            ->size(ActionSize::ExtraLarge)
            ->iconSize(IconSize::Large)
            ->extraAttributes(['class' => '[&_svg]:w-8 [&_svg]:h-8'])
            ->icon(AttendanceStatus::PRESENT->getIcon())
            ->color(AttendanceStatus::PRESENT->getColor())
            ->hidden(fn (Memorizer $record) => $record->attendances()
                ->whereDate('date', now()->toDateString())
                ->exists())
            ->action(function (Memorizer $record): void {
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
            });
    }
}
