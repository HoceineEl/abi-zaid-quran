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

        // Get unique students count by phone number
        $uniqueStudents = Memorizer::whereBetween('created_at', [$dateFrom, $dateTo])->count();

        // Calculate total payments for the filtered period
        $periodPayments = Payment::whereBetween('payment_date', [$dateFrom, $dateTo])
            ->sum('amount');

        // Get students who haven't paid in the filtered period
        $unpaidStudents = Memorizer::whereDoesntHave('payments', function ($query) use ($dateFrom, $dateTo) {
            $query->whereBetween('payment_date', [$dateFrom, $dateTo]);
        })->where('exempt', false)->count();

        return [
            Stat::make('إجمالي الطلاب', Number::format($uniqueStudents))
                ->description(sprintf(
                    'ذكور: %d | إناث: %d',
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

            Stat::make('المدفوعات للفترة', Number::format($periodPayments) . ' درهم')
                ->description(sprintf(
                    'دفع: %d | لم يدفع: %d',
                    Memorizer::whereHas('payments', function ($query) use ($dateFrom, $dateTo) {
                        $query->whereBetween('payment_date', [$dateFrom, $dateTo]);
                    })->where('exempt', false)->count(),
                    $unpaidStudents
                ))
                ->descriptionIcon('heroicon-m-banknotes')
                ->chart([2000, 1500, 2400, $periodPayments])
                ->color('warning'),

            Stat::make('عدد المجموعات النشطة', MemoGroup::count())
                ->descriptionIcon('heroicon-m-user-group')
                ->color('success'),

            Stat::make('عدد المعلمين', User::where('role', 'teacher')->count())
                ->description(sprintf(
                    'ذكور: %d | إناث: %d',
                    User::where('role', 'teacher')->where('sex', 'male')->count(),
                    User::where('role', 'teacher')->where('sex', 'female')->count()
                ))
                ->descriptionIcon('heroicon-m-user-group')
                ->color('success'),
        ];
    }
}
