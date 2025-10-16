<?php

namespace App\Filament\Association\Widgets;

use App\Models\Payment;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Carbon;

class PaymentsChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'تحليل الرسوم المحصّلة';
    protected ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $dateFrom = $this->pageFilters['date_from'] ?? now()->startOfYear();
        $dateTo = $this->pageFilters['date_to'] ?? now();

        $payments = Payment::whereBetween('created_at', [$dateFrom, $dateTo])
            ->selectRaw('DATE(created_at) as date, SUM(amount) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'الرسوم المحصّلة يومياً (درهم)',
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
