@php
    /** @var \Illuminate\Support\Collection $payments */
@endphp

<div class="space-y-3 text-sm">
    <div class="flex items-center gap-2 rounded-lg bg-success-50 p-3 text-success-700 dark:bg-success-500/10 dark:text-success-400">
        <x-filament::icon icon="heroicon-s-check-circle" class="h-5 w-5" />
        <span class="font-semibold">تم دفع شهر {{ $month['label'] }}</span>
    </div>

    <div class="overflow-hidden rounded-lg ring-1 ring-gray-200 dark:ring-white/10">
        <table class="w-full text-right">
            <thead class="bg-gray-50 text-xs text-gray-500 dark:bg-white/5 dark:text-gray-400">
                <tr>
                    <th class="px-3 py-2 font-medium">المبلغ</th>
                    <th class="px-3 py-2 font-medium">تاريخ الدفع</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                @foreach ($payments as $payment)
                    <tr class="text-gray-700 dark:text-gray-200">
                        <td class="px-3 py-2 font-semibold">{{ number_format((float) $payment->amount) }} درهم</td>
                        <td class="px-3 py-2" dir="ltr">{{ $payment->payment_date?->format('Y-m-d') }}</td>
                    </tr>
                @endforeach
            </tbody>
            @if ($payments->count() > 1)
                <tfoot class="bg-gray-50 font-bold dark:bg-white/5">
                    <tr>
                        <td class="px-3 py-2">{{ number_format($total) }} درهم</td>
                        <td class="px-3 py-2 text-gray-400">المجموع</td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>
