<?php

namespace App\Services;

use App\Models\Memorizer;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;

class YearlyPaymentPdfService
{
    private const ARABIC_MONTHS = [
        1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل',
        5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'غشت',
        9 => 'شتنبر', 10 => 'أكتوبر', 11 => 'نونبر', 12 => 'دجنبر',
    ];

    public function generate(Carbon $startDate, Carbon $endDate): string
    {
        // Defensive: rendering hundreds of memorizers across many months
        // pushes mPDF well past the default 128M PHP memory limit.
        @ini_set('memory_limit', '512M');
        @ini_set('pcre.backtrack_limit', '5000000');
        @set_time_limit(300);

        if ($endDate->lt($startDate)) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        $start = $startDate->copy()->startOfMonth();
        $end = $endDate->copy()->endOfMonth();

        $months = $this->monthsInRange($start, $end);
        $rows = $this->buildRows($startDate, $endDate, $months);
        $totals = $this->columnTotals($rows, $months);

        $html = view('exports.yearly-payment-pdf', [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'months' => $months,
            'rows' => $rows,
            'totals' => $totals,
            'arabicMonths' => self::ARABIC_MONTHS,
        ])->render();

        return $this->renderPdf($html, $months);
    }

    /**
     * @return array<int, array{year:int, month:int, key:string, label:string}>
     */
    private function monthsInRange(Carbon $start, Carbon $end): array
    {
        $months = [];

        foreach (CarbonPeriod::create($start, '1 month', $end) as $cursor) {
            $year = (int) $cursor->format('Y');
            $month = (int) $cursor->format('n');

            $months[] = [
                'year' => $year,
                'month' => $month,
                'key' => sprintf('%04d-%02d', $year, $month),
                'label' => self::ARABIC_MONTHS[$month].' '.$year,
            ];
        }

        return $months;
    }

    /**
     * @param  array<int, array{year:int, month:int, key:string, label:string}>  $months
     * @return array<int, array<string, mixed>>
     */
    private function buildRows(Carbon $startDate, Carbon $endDate, array $months): array
    {
        $memorizers = Memorizer::query()
            ->with([
                'group:id,name',
                'guardian:id,phone',
                'payments' => fn ($q) => $q->whereBetween('payment_date', [
                    $startDate->copy()->startOfDay(),
                    $endDate->copy()->endOfDay(),
                ]),
            ])
            ->orderBy('name')
            ->get();

        return $memorizers->values()->map(function (Memorizer $memorizer, int $index) use ($months) {
            $cells = [];
            $rowTotal = 0.0;
            $paidCount = 0;

            $byKey = $memorizer->payments
                ->groupBy(fn ($p) => $p->payment_date->format('Y-m'))
                ->map(fn ($payments) => (float) $payments->sum('amount'));

            foreach ($months as $month) {
                if ($memorizer->exempt) {
                    $cells[$month['key']] = ['type' => 'exempt'];

                    continue;
                }

                if ($byKey->has($month['key'])) {
                    $amount = $byKey->get($month['key']);
                    $cells[$month['key']] = ['type' => 'paid', 'amount' => $amount];
                    $rowTotal += $amount;
                    $paidCount++;
                } else {
                    $cells[$month['key']] = ['type' => 'unpaid'];
                }
            }

            return [
                'index' => $index + 1,
                'name' => $memorizer->name,
                'phone' => $this->formatPhone($memorizer->displayPhone),
                'group' => $memorizer->group?->name ?? '—',
                'exempt' => (bool) $memorizer->exempt,
                'cells' => $cells,
                'row_total' => $rowTotal,
                'paid_count' => $paidCount,
                'months_count' => count($months),
            ];
        })->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, array{year:int, month:int, key:string, label:string}>  $months
     * @return array{by_month: array<string, float>, grand_total: float}
     */
    private function columnTotals(array $rows, array $months): array
    {
        $byMonth = [];
        foreach ($months as $month) {
            $byMonth[$month['key']] = 0.0;
        }
        $grand = 0.0;

        foreach ($rows as $row) {
            foreach ($row['cells'] as $key => $cell) {
                if (($cell['type'] ?? null) === 'paid') {
                    $byMonth[$key] += $cell['amount'];
                    $grand += $cell['amount'];
                }
            }
        }

        return ['by_month' => $byMonth, 'grand_total' => $grand];
    }

    /**
     * @param  array<int, array{year:int, month:int, key:string, label:string}>  $months
     */
    private function renderPdf(string $html, array $months): string
    {
        // mPDF parses the full HTML with PCRE; raise the limit to handle long lists.
        @ini_set('pcre.backtrack_limit', '5000000');

        $defaultConfig = (new ConfigVariables)->getDefaults();
        $defaultFontConfig = (new FontVariables)->getDefaults();

        // Wider sheets get landscape; very wide ranges get a larger page format.
        $monthCount = count($months);
        $orientation = $monthCount <= 4 ? 'P' : 'L';
        $format = $monthCount > 10 ? 'A3' : 'A4';

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => $format,
            'orientation' => $orientation,
            'margin_left' => 8,
            'margin_right' => 8,
            'margin_top' => 10,
            'margin_bottom' => 12,
            'fontDir' => array_merge($defaultConfig['fontDir'], [storage_path('fonts')]),
            'fontdata' => array_merge($defaultFontConfig['fontdata'], [
                'cairo' => [
                    'R' => 'Cairo-Regular.ttf',
                    'B' => 'Cairo-Bold.ttf',
                ],
            ]),
            'default_font' => 'cairo',
            'default_font_size' => 9,
        ]);

        $mpdf->SetDirectionality('rtl');
        $mpdf->autoScriptToLang = true;
        $mpdf->autoLangToFont = true;
        $mpdf->shrink_tables_to_fit = 1;
        $mpdf->SetDisplayMode('fullpage');

        $mpdf->WriteHTML($html);

        return $mpdf->Output('', 'S');
    }

    private function formatPhone(?string $phone): string
    {
        if (blank($phone)) {
            return '—';
        }

        try {
            return phone($phone, 'MA')->formatNational();
        } catch (\Throwable) {
            return $phone;
        }
    }
}
