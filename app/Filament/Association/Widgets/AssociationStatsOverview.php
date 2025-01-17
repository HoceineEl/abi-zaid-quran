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
use Illuminate\Support\Carbon;

class AssociationStatsOverview extends BaseWidget
{
    use InteractsWithPageFilters;

    protected static ?string $pollingInterval = '300s';

    protected function getStats(): array
    {
        $dateFrom = isset($this->filters['date_from']) ? Carbon::parse($this->filters['date_from']) : now()->startOfYear();
        $dateTo = isset($this->filters['date_to']) ? Carbon::parse($this->filters['date_to']) : now();

        // إحصاء عدد الطلاب المسجلين في الفترة المحددة
        $uniqueStudents = Memorizer::whereBetween('created_at', [$dateFrom, $dateTo])->count();

        // حساب إجمالي الرسوم المحصّلة في الفترة المحددة
        $periodPayments = Payment::whereBetween('created_at', [$dateFrom, $dateTo])
            ->sum('amount');

        // إحصاء الطلاب غير المسددين للرسوم في الفترة المحددة
        $unpaidStudents = Memorizer::whereDoesntHave('payments', function ($query) use ($dateFrom, $dateTo) {
            $query->whereBetween('payment_date', [$dateFrom, $dateTo]);
        })->where('exempt', false)->count();

        return [
            Stat::make('إحصائيات الطلاب المسجلين', Number::format($uniqueStudents))
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

            Stat::make('الرسوم المحصّلة للفترة من ' . $dateFrom->format('d/m/Y') . ' إلى ' . $dateTo->format('d/m/Y'), Number::format($periodPayments) . ' درهم')
                ->description(new \Illuminate\Support\HtmlString(sprintf(
                    '
                    <div class="flex flex-col gap-1">
                        <div class="text-info-500">الطلاب المسدّدون للرسوم: %d</div>
                        <div class="text-warning-500">الطالبات المسدّدات للرسوم: %d</div>
                        <div class="text-info-500">الطلاب غير المسدّدين للرسوم: %d</div>
                        <div class="text-warning-500">الطالبات غير المسدّدات للرسوم: %d</div>
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
                ->chart([2000, 1500, 2400, $periodPayments]),

            Stat::make('عدد الحلقات القرآنية النشطة', MemoGroup::count())
                ->descriptionIcon('heroicon-m-user-group')
                ->color('success'),

            Stat::make('إحصائيات هيئة التدريس', User::where('role', 'teacher')->count())
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
