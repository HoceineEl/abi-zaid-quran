<?php

namespace App\Filament\Actions\Attendance;

use Filament\Actions\Action;
use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Memorizer;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class JustifyPastAbsenceAction extends Action
{
    private const LOOKBACK_DAYS = 30;

    public static function getDefaultName(): ?string
    {
        return 'justify_past_absence';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->tooltip('تبرير غيابات سابقة')
            ->label('')
            ->icon('heroicon-o-calendar-days')
            ->color(AttendanceStatus::ABSENT_JUSTIFIED->getColor())
            ->slideOver()
            ->modalHeading('تبرير غيابات سابقة')
            ->modalSubmitActionLabel('تبرير المحدد')
            ->form(function (Memorizer $record): array {
                $absences = self::getUnjustifiedAbsences($record);

                $options = $absences->mapWithKeys(fn (Attendance $a) => [
                    $a->id => $a->date->format('Y-m-d') . ' (' . self::arabicDayName($a->date->dayOfWeek) . ')',
                ])->toArray();

                return [
                    CheckboxList::make('absence_ids')
                        ->label('اختر الغيابات لتبريرها')
                        ->options($options)
                        ->columns(1)
                        ->bulkToggleable(),
                ];
            })
            ->visible(fn (Memorizer $record): bool => self::getUnjustifiedAbsences($record)->isNotEmpty())
            ->action(function (Memorizer $record, array $data): void {
                $ids = $data['absence_ids'] ?? [];

                if (empty($ids)) {
                    Notification::make()
                        ->title('لم يتم تحديد أي غيابات')
                        ->warning()
                        ->send();

                    return;
                }

                Attendance::whereIn('id', $ids)
                    ->where('memorizer_id', $record->id)
                    ->update(['absence_justified' => true]);

                Notification::make()
                    ->title('تم تبرير ' . count($ids) . ' غياب بنجاح')
                    ->success()
                    ->send();
            });
    }

    /**
     * Get unjustified absences within the lookback period as a Collection.
     *
     * We return a Collection (not Builder/HasMany) to avoid the type mismatch
     * that occurs because HasMany's static context changes the return type.
     */
    private static function getUnjustifiedAbsences(Memorizer $record): Collection
    {
        return $record->attendances()
            ->whereNull('check_in_time')
            ->where(fn (Builder $q) => $q
                ->where('absence_justified', false)
                ->orWhereNull('absence_justified'))
            ->where('date', '>=', now()->subDays(self::LOOKBACK_DAYS)->toDateString())
            ->orderByDesc('date')
            ->get();
    }

    private static function arabicDayName(int $dayOfWeek): string
    {
        return match ($dayOfWeek) {
            0 => 'الأحد',
            1 => 'الاثنين',
            2 => 'الثلاثاء',
            3 => 'الأربعاء',
            4 => 'الخميس',
            5 => 'الجمعة',
            6 => 'السبت',
        };
    }
}
