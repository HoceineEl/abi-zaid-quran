<?php

namespace App\Filament\Association\Widgets;

use App\Models\Attendance;
use App\Models\MemoGroup;
use App\Models\Memorizer;
use App\Models\Payment;
use App\Models\Teacher;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Number;

class AssociationStatsOverview extends BaseWidget
{
    use InteractsWithPageFilters;

    protected static ?string $pollingInterval = '300s';

    protected function getStats(): array
    {
        $dateFrom = $this->filters['date_from'] ?? now()->startOfYear();
        $dateTo = $this->filters['date_to'] ?? now();

        // حساب عدد الطلاب الفريدين حسب رقم الهاتف
        $uniqueStudents = Memorizer::whereBetween('created_at', [$dateFrom, $dateTo])->count();

        // حساب إجمالي المدفوعات للفترة المحددة
        $periodPayments = Payment::whereBetween('created_at', [$dateFrom, $dateTo])
            ->sum('amount');

        // حساب عدد الطلاب الذين لم يسددوا الرسوم في الفترة المحددة
        $unpaidStudents = Memorizer::whereDoesntHave('payments', function ($query) use ($dateFrom, $dateTo) {
            $query->whereBetween('payment_date', [$dateFrom, $dateTo]);
        })->where('exempt', false)->count();

        return [
            Stat::make('العدد الإجمالي للطلاب', Number::format($uniqueStudents))
                ->description(sprintf(
                    'الطلاب: %d | الطالبات: %d',
                    Memorizer::whereHas('teacher', function ($query) {
                        $query->where('sex', 'male');
                    })->whereBetween('created_at', [$dateFrom, $dateTo])->count(),
                    Memorizer::whereHas('teacher', function ($query) {
                        $query->where('sex', 'female');
                    })->whereBetween('created_at', [$dateFrom, $dateTo])->count()
                ))
                ->descriptionIcon('heroicon-m-users')
                ->chart([7, 3, 4, 5, 6, $uniqueStudents])
                ->color('success'),

            Stat::make('المدفوعات من ' . $dateFrom->format('d/m/Y') . ' حتى ' . $dateTo->format('d/m/Y'), Number::format($periodPayments) . ' درهم')
                ->description(new \Illuminate\Support\HtmlString(sprintf('
                    <div class="flex flex-col gap-1">
                        <div>الطلاب المسددون: %d</div>
                        <div>الطالبات المسددات: %d</div>
                        <div>الطلاب غير المسددين: %d</div>
                        <div>الطالبات غير المسددات: %d</div>
                    </div>',
                    Memorizer::whereHas('payments', function ($query) use ($dateFrom, $dateTo) {
                        $query->whereBetween('payment_date', [$dateFrom, $dateTo]);
                    })
                    ->whereHas('teacher', function ($query) {
                        $query->where('sex', 'male');
                    })
                    ->where('exempt', false)
                    ->count(),
                    Memorizer::whereHas('payments', function ($query) use ($dateFrom, $dateTo) {
                        $query->whereBetween('payment_date', [$dateFrom, $dateTo]);
                    })
                    ->whereHas('teacher', function ($query) {
                        $query->where('sex', 'female');
                    })
                    ->where('exempt', false)
                    ->count(),
                    Memorizer::whereDoesntHave('payments', function ($query) use ($dateFrom, $dateTo) {
                        $query->whereBetween('payment_date', [$dateFrom, $dateTo]);
                    })
                    ->whereHas('teacher', function ($query) {
                        $query->where('sex', 'male');
                    })
                    ->where('exempt', false)
                    ->count(),
                    Memorizer::whereDoesntHave('payments', function ($query) use ($dateFrom, $dateTo) {
                        $query->whereBetween('payment_date', [$dateFrom, $dateTo]);
                    })
                    ->whereHas('teacher', function ($query) {
                        $query->where('sex', 'female');
                    })
                    ->where('exempt', false)
                    ->count()
                )))
                ->chart([2000, 1500, 2400, $periodPayments])
                ->color('warning'),

            Stat::make('عدد الحلقات النشطة', MemoGroup::count())
                ->descriptionIcon('heroicon-m-user-group')
                ->color('success'),

            Stat::make('عدد المعلمين والمعلمات', User::where('role', 'teacher')->count())
                ->description(sprintf(
                    'المعلمون: %d | المعلمات: %d',
                    User::where('role', 'teacher')->where('sex', 'male')->count(),
                    User::where('role', 'teacher')->where('sex', 'female')->count()
                ))
                ->descriptionIcon('heroicon-m-user-group')
                ->color('success'),
        ];
    }
}
