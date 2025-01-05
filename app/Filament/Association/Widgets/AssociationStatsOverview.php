<?php

namespace App\Filament\Association\Widgets;

use App\Models\Attendance;
use App\Models\MemoGroup;
use App\Models\Memorizer;
use App\Models\Payment;
use App\Models\Teacher;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

class AssociationStatsOverview extends BaseWidget
{
    protected static ?string $pollingInterval = '15s';

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

        // Calculate attendance percentage for today
        $totalStudents = Memorizer::count();
        $presentToday = Attendance::whereDate('date', now())
            ->whereNotNull('check_in_time')
            ->count();
        $attendancePercentage = $totalStudents > 0
            ? round(($presentToday / $totalStudents) * 100, 1)
            : 0;

        return [
            Stat::make('إجمالي الطلاب', Number::format($uniqueStudents))
                ->description('عدد الطلاب')
                ->descriptionIcon('heroicon-m-users')
                ->chart([7, 3, 4, 5, 6, $uniqueStudents])
                ->color('success'),

            Stat::make('المدفوعات الشهرية', Number::format($monthlyPayments) . ' درهم')
                ->description(sprintf('%d طالب لم يدفع بعد', $unpaidStudents))
                ->descriptionIcon('heroicon-m-banknotes')
                ->chart([2000, 1500, 2400, $monthlyPayments])
                ->color('warning'),

            Stat::make('نسبة الحضور اليوم', $attendancePercentage . '%')
                ->description(sprintf('%d طالب حاضر من أصل %d', $presentToday, $totalStudents))
                ->descriptionIcon('heroicon-m-academic-cap')
                ->chart([65, 75, 80, $attendancePercentage])
                ->color('info'),

            Stat::make('عدد المجموعات النشطة', MemoGroup::count())
                ->description(sprintf('%d معلم مسجل', Teacher::count()))
                ->descriptionIcon('heroicon-m-user-group')
                ->color('success'),
            Stat::make('عدد المعلمين', Teacher::count())
                ->description(sprintf('%d معلم مسجل', Teacher::count()))
                ->descriptionIcon('heroicon-m-user-group')
                ->color('success'),
        ];
    }
}
