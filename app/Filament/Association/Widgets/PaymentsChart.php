<?php

namespace App\Filament\Association\Widgets;

use App\Models\Payment;
use Filament\Forms\Components\DatePicker;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class PaymentsChart extends ChartWidget
{
    protected static ?string $heading = 'إحصائيات المدفوعات';
    protected static ?string $maxHeight = '300px';

    public ?string $fromDate = null;
    public ?string $toDate = null;

    protected function getFormSchema(): array
    {
        return [
            DatePicker::make('fromDate')
                ->label('من تاريخ')
                ->default(now()->startOfMonth()),
            DatePicker::make('toDate')
                ->label('إلى تاريخ')
                ->default(now()->endOfMonth()),
        ];
    }

    protected function getData(): array
    {
        $startDate = $this->fromDate ? Carbon::parse($this->fromDate) : now()->startOfMonth();
        $endDate = $this->toDate ? Carbon::parse($this->toDate) : now()->endOfMonth();

        $payments = Payment::whereBetween('payment_date', [$startDate, $endDate])
            ->selectRaw('DATE(payment_date) as date, SUM(amount) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'المدفوعات اليومية (درهم)',
                    'data' => $payments->pluck('total')->toArray(),
                    'borderColor' => '#10B981',
                    'fill' => 'start',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                ],
            ],
            'labels' => $payments->map(fn($payment) => Carbon::parse($payment->date)->format('Y-m-d'))->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
