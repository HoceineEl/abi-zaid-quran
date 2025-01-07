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
use Illuminate\Support\Number;

class AssociationStatsOverview extends BaseWidget
{
    protected static ?string $pollingInterval = '300s';

    protected function getStats(): array
    {
        // Get unique students count by phone number
        $uniqueStudents = Memorizer::count();

        // Calculate total payments this month
        $monthlyPayments = Payment::whereMonth('payment_date', now()->month)
            ->whereYear('payment_date', now()->year)
            ->sum('amount');

        // Get students who haven't paid this month
        $unpaidStudents = Memorizer::whereDoesntHave('payments', function ($query) {
            $query->whereMonth('payment_date', now()->month)
                ->whereYear('payment_date', now()->year);
        })->where('exempt', false)->count();


        return [
            Stat::make('إجمالي الطلاب', Number::format($uniqueStudents))
                ->description(sprintf(
                    'ذكور: %d | إناث: %d',
                    Memorizer::whereHas('teacher', function ($query) {
                        $query->where('sex', 'male');
                    })->count(),
                    Memorizer::whereHas('teacher', function ($query) {
                        $query->where('sex', 'female');
                    })->count()
                ))
                ->descriptionIcon('heroicon-m-users')
                ->chart([7, 3, 4, 5, 6, $uniqueStudents])
                ->color('success'),

            Stat::make('المدفوعات الشهرية', Number::format($monthlyPayments) . ' درهم')
                ->description(sprintf('%d طالب لم يدفع بعد', $unpaidStudents))
                ->descriptionIcon('heroicon-m-banknotes')
                ->chart([2000, 1500, 2400, $monthlyPayments])
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
