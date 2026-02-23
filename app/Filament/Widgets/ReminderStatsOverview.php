<?php

namespace App\Filament\Widgets;

use App\Helpers\PhoneHelper;
use App\Models\Group;
use App\Models\Student;
use App\Models\WhatsAppMessageHistory;
use Carbon\Carbon;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ReminderStatsOverview extends BaseWidget
{
    use InteractsWithPageFilters;

    protected static bool $isDiscovered = false;

    protected static ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $date = $this->filters['date'] ?? now()->toDateString();

        $remindedPhones = WhatsAppMessageHistory::query()
            ->whereDate('created_at', $date)
            ->pluck('recipient_phone');

        $remindedPhonesSet = array_flip($remindedPhones->toArray());

        $students = Student::query()
            ->whereNotNull('phone')
            ->get(['id', 'phone', 'group_id']);

        $remindedGroupIds = $students
            ->filter(function ($s) use ($remindedPhonesSet) {
                $cleaned = PhoneHelper::cleanPhoneNumber($s->phone);

                return $cleaned && isset($remindedPhonesSet[$cleaned]);
            })
            ->pluck('group_id')
            ->unique();

        $totalGroups = Group::count();
        $remindedCount = Group::whereIn('id', $remindedGroupIds)->count();
        $notRemindedCount = $totalGroups - $remindedCount;
        $totalMessages = $remindedPhones->count();
        $percentage = $totalGroups > 0 ? round(($remindedCount / $totalGroups) * 100) : 0;

        return [
            Stat::make('إجمالي المجموعات', $totalGroups)
                ->description('عدد المجموعات الكلي')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('primary'),

            Stat::make('مجموعات تم تذكيرها', $remindedCount)
                ->description($percentage.'% من المجموعات')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make('مجموعات لم يتم تذكيرها', $notRemindedCount)
                ->description($notRemindedCount > 0 ? 'تحتاج متابعة' : 'ممتاز!')
                ->descriptionIcon($notRemindedCount > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($notRemindedCount > 0 ? 'danger' : 'success'),

            Stat::make('إجمالي الرسائل المرسلة', $totalMessages)
                ->description(Carbon::parse($date)->translatedFormat('d F Y'))
                ->descriptionIcon('heroicon-m-chat-bubble-left-right')
                ->color('info'),
        ];
    }
}
