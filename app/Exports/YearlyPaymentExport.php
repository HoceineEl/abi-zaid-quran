<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Memorizer;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Style;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class YearlyPaymentExport implements FromArray, WithHeadings, WithTitle, WithEvents
{
    private const STATUS_UNPAID = 'غير مدفوع';
    private const STATUS_EXEMPT = 'معفي';
    private const CHECKBOX_OFF = '☐';
    private const CHECKBOX_ON = '☑';
    private const PAID_FORMAT = '"مدفوع · "#,##0';
    private const CURRENCY_FORMAT = '#,##0 "د.م"';

    private const COLOR_WHITE = 'FFFFFFFF';
    private const COLOR_EMERALD = 'FF0D5D3F';
    private const COLOR_GOLD = 'FFB8860B';
    private const COLOR_CREAM = 'FFFAF4E3';
    private const COLOR_GOLD_TEXT = 'FF8B6914';

    private const PALETTE_PAID = ['FFC6EFCE', 'FF006100'];
    private const PALETTE_UNPAID = ['FFFFC7CE', 'FF9C0006'];
    private const PALETTE_EXEMPT = ['FFDDEBF7', 'FF0066CC'];
    private const PALETTE_NEUTRAL = ['FFF2F2F2', 'FF7F7F7F'];
    private const PALETTE_PARTIAL = ['FFFFF2CC', 'FF8B6914'];

    private const ARABIC_MONTHS = [
        1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل',
        5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'غشت',
        9 => 'شتنبر', 10 => 'أكتوبر', 11 => 'نونبر', 12 => 'دجنبر',
    ];

    private const FIXED_LEADING_COLUMNS = 4;

    private array $months;
    private array $rows;

    public function __construct(
        private Carbon $startDate,
        private Carbon $endDate,
    ) {
        $start = $startDate->copy()->startOfMonth();
        $end = $endDate->copy()->endOfMonth();

        $this->months = $this->monthsInRange($start, $end);
        $this->rows = $this->buildRows();
    }

    public function title(): string
    {
        return 'متابعة أداء الواجب';
    }

    public function headings(): array
    {
        $headers = ['#', 'الاسم', 'رقم الهاتف', 'المجموعة'];

        foreach ($this->months as $month) {
            $headers[] = $month['label'];
        }

        return [...$headers, 'المجموع (د.م)', 'الملخص', 'تم التواصل', 'نتيجة التواصل'];
    }

    public function array(): array
    {
        $data = [];
        $monthCount = count($this->months);

        foreach ($this->rows as $row) {
            $line = [$row['index'], $row['name'], $row['phone'], $row['group']];

            if ($row['exempt']) {
                foreach ($this->months as $ignored) {
                    $line[] = self::STATUS_EXEMPT;
                }
                $line[] = self::STATUS_EXEMPT;
                $line[] = self::STATUS_EXEMPT;
            } else {
                $paidCount = 0;
                foreach ($this->months as $month) {
                    $cell = $row['cells'][$month['key']];
                    if ($cell['type'] === 'paid') {
                        $line[] = $cell['amount'];
                        $paidCount++;
                    } else {
                        $line[] = self::STATUS_UNPAID;
                    }
                }
                $line[] = $row['row_total'];
                $line[] = "{$paidCount} / {$monthCount}";
            }

            $line[] = self::CHECKBOX_OFF;
            $line[] = '';

            $data[] = $line;
        }

        return $data;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->setRightToLeft(true);

                $columns = $this->columnLayout();
                $headerRow = 3;
                $firstDataRow = $headerRow + 1;

                $this->renderTitleBlock($sheet, $columns['contact']);
                $highestRow = $sheet->getHighestRow();

                $this->styleHeaderRow($sheet, $headerRow, $columns['contact']);
                $this->styleDataRows($sheet, $firstDataRow, $highestRow, $columns);
                $totalsRow = $this->appendTotalsRow($sheet, $firstDataRow, $highestRow, $columns);
                $this->setColumnWidths($sheet, $columns);
                $this->applyContactedCheckbox($sheet, $firstDataRow, $highestRow, $columns['contacted']);

                $sheet->freezePane('E'.$firstDataRow);

                $finalRow = $totalsRow ?: $highestRow;
                $sheet->getStyle("A{$headerRow}:{$columns['contact']}{$finalRow}")
                    ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            },
        ];
    }

    private function columnLayout(): array
    {
        $base = self::FIXED_LEADING_COLUMNS + count($this->months);

        return [
            'row_total' => Coordinate::stringFromColumnIndex($base + 1),
            'summary' => Coordinate::stringFromColumnIndex($base + 2),
            'contacted' => Coordinate::stringFromColumnIndex($base + 3),
            'contact' => Coordinate::stringFromColumnIndex($base + 4),
        ];
    }

    private function renderTitleBlock(Worksheet $sheet, string $lastColumn): void
    {
        $sheet->insertNewRowBefore(1, 2);

        $label = $this->startDate->format('Y-m-d').' — '.$this->endDate->format('Y-m-d');
        $sheet->mergeCells("A1:{$lastColumn}1");
        $sheet->setCellValue('A1', "متابعة أداء الواجب · {$label}");
        $sheet->getRowDimension(1)->setRowHeight(32);
        $this->applyBlock($sheet->getStyle('A1'), self::COLOR_EMERALD, self::COLOR_WHITE, bold: true, size: 16);

        $sheet->mergeCells("A2:{$lastColumn}2");
        $sheet->setCellValue('A2', 'تاريخ التقرير: '.now()->format('Y-m-d').'  ·  مدفوع · غير مدفوع · معفي');
        $sheet->getRowDimension(2)->setRowHeight(20);
        $this->applyBlock($sheet->getStyle('A2'), self::COLOR_CREAM, self::COLOR_GOLD_TEXT, bold: true, size: 11);
    }

    private function styleHeaderRow(Worksheet $sheet, int $row, string $lastColumn): void
    {
        $this->applyBlock(
            $sheet->getStyle("A{$row}:{$lastColumn}{$row}"),
            self::COLOR_EMERALD,
            self::COLOR_WHITE,
            bold: true,
            size: 11,
        );
        $sheet->getRowDimension($row)->setRowHeight(28);
    }

    private function styleDataRows(Worksheet $sheet, int $firstDataRow, int $highestRow, array $columns): void
    {
        if ($highestRow < $firstDataRow) {
            return;
        }

        $monthCount = count($this->months);

        for ($row = $firstDataRow; $row <= $highestRow; $row++) {
            $sheet->getRowDimension($row)->setRowHeight(22);
            $stripeColor = ($row - $firstDataRow) % 2 === 0 ? self::COLOR_WHITE : self::COLOR_CREAM;

            $this->applyFill($sheet->getStyle("A{$row}:D{$row}"), $stripeColor);
            $sheet->getStyle("A{$row}:D{$row}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("B{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("B{$row}")->getFont()->setBold(true);
            $sheet->getStyle("C{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("D{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            for ($i = 1; $i <= $monthCount; $i++) {
                $cell = $this->monthColumn($i).$row;
                $value = $sheet->getCell($cell)->getValue();
                $this->paintCell($sheet, $cell, $this->statusColors($value));

                if (is_numeric($value)) {
                    $sheet->getStyle($cell)->getNumberFormat()->setFormatCode(self::PAID_FORMAT);
                }
            }

            $rowTotalCell = $columns['row_total'].$row;
            $rowTotalValue = $sheet->getCell($rowTotalCell)->getValue();
            $this->paintCell($sheet, $rowTotalCell, $this->rowTotalColors($rowTotalValue));
            if (is_numeric($rowTotalValue)) {
                $sheet->getStyle($rowTotalCell)->getNumberFormat()->setFormatCode(self::CURRENCY_FORMAT);
            }

            $summaryCell = $columns['summary'].$row;
            $this->paintCell($sheet, $summaryCell, $this->summaryColors($sheet->getCell($summaryCell)->getValue()));

            $contactedStyle = $sheet->getStyle($columns['contacted'].$row);
            $this->applyFill($contactedStyle, $stripeColor);
            $contactedStyle->getFont()->setSize(14)->getColor()->setARGB(self::COLOR_EMERALD);
            $contactedStyle->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER);

            $contactStyle = $sheet->getStyle($columns['contact'].$row);
            $this->applyFill($contactStyle, $stripeColor);
            $contactStyle->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_RIGHT)
                ->setVertical(Alignment::VERTICAL_CENTER)
                ->setWrapText(true);
        }
    }

    private function appendTotalsRow(Worksheet $sheet, int $firstDataRow, int $highestRow, array $columns): int
    {
        if ($highestRow < $firstDataRow) {
            return 0;
        }

        $totalsRow = $highestRow + 1;
        $rowTotalCol = $columns['row_total'];
        $monthCount = count($this->months);

        $sheet->getRowDimension($totalsRow)->setRowHeight(30);
        $sheet->mergeCells("A{$totalsRow}:D{$totalsRow}");
        $sheet->setCellValue("A{$totalsRow}", 'الإجمالي (د.م)');

        for ($i = 1; $i <= $monthCount; $i++) {
            $col = $this->monthColumn($i);
            $sheet->setCellValue("{$col}{$totalsRow}", "=SUM({$col}{$firstDataRow}:{$col}{$highestRow})");
        }

        $sheet->setCellValue("{$rowTotalCol}{$totalsRow}", "=SUM({$rowTotalCol}{$firstDataRow}:{$rowTotalCol}{$highestRow})");

        $rowStyle = $sheet->getStyle("A{$totalsRow}:{$columns['contact']}{$totalsRow}");
        $this->applyBlock($rowStyle, self::COLOR_EMERALD, self::COLOR_WHITE, bold: true, size: 12);
        $rowStyle->getBorders()->getTop()
            ->setBorderStyle(Border::BORDER_MEDIUM)
            ->getColor()->setARGB(self::COLOR_GOLD);

        $firstMonthCol = $this->monthColumn(1);
        $sheet->getStyle("{$firstMonthCol}{$totalsRow}:{$rowTotalCol}{$totalsRow}")
            ->getNumberFormat()->setFormatCode(self::CURRENCY_FORMAT);

        $rowTotalStyle = $sheet->getStyle("{$rowTotalCol}{$totalsRow}");
        $this->applyFill($rowTotalStyle, self::COLOR_GOLD);
        $rowTotalStyle->getFont()->setBold(true)->setSize(13)->getColor()->setARGB(self::COLOR_WHITE);

        return $totalsRow;
    }

    private function applyContactedCheckbox(Worksheet $sheet, int $firstDataRow, int $highestRow, string $contactedCol): void
    {
        if ($highestRow < $firstDataRow) {
            return;
        }

        for ($row = $firstDataRow; $row <= $highestRow; $row++) {
            $validation = $sheet->getCell($contactedCol.$row)->getDataValidation();
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setErrorStyle(DataValidation::STYLE_INFORMATION);
            $validation->setAllowBlank(false);
            $validation->setShowInputMessage(true);
            $validation->setShowErrorMessage(true);
            $validation->setShowDropDown(true);
            $validation->setPromptTitle('تم التواصل');
            $validation->setPrompt('اختر ☑ إذا تم التواصل مع الطالب أو وليّ أمره.');
            $validation->setFormula1('"'.self::CHECKBOX_OFF.','.self::CHECKBOX_ON.'"');
        }
    }

    private function setColumnWidths(Worksheet $sheet, array $columns): void
    {
        $sheet->getColumnDimension('A')->setWidth(5);
        $sheet->getColumnDimension('B')->setWidth(28);
        $sheet->getColumnDimension('C')->setWidth(18);
        $sheet->getColumnDimension('D')->setWidth(22);

        for ($i = 1; $i <= count($this->months); $i++) {
            $sheet->getColumnDimension($this->monthColumn($i))->setWidth(16);
        }

        $sheet->getColumnDimension($columns['row_total'])->setWidth(16);
        $sheet->getColumnDimension($columns['summary'])->setWidth(12);
        $sheet->getColumnDimension($columns['contacted'])->setWidth(8);
        $sheet->getColumnDimension($columns['contact'])->setWidth(40);
    }

    private function statusColors(mixed $value): array
    {
        if (is_numeric($value)) {
            return self::PALETTE_PAID;
        }

        return match ($value) {
            self::STATUS_UNPAID => self::PALETTE_UNPAID,
            self::STATUS_EXEMPT => self::PALETTE_EXEMPT,
            default => self::PALETTE_NEUTRAL,
        };
    }

    private function rowTotalColors(mixed $value): array
    {
        if ($value === self::STATUS_EXEMPT) {
            return self::PALETTE_EXEMPT;
        }

        if (is_numeric($value) && $value > 0) {
            return self::PALETTE_PARTIAL;
        }

        return self::PALETTE_NEUTRAL;
    }

    private function summaryColors(?string $value): array
    {
        if ($value === self::STATUS_EXEMPT) {
            return self::PALETTE_EXEMPT;
        }

        $monthCount = count($this->months);
        $paidCount = (int) explode('/', (string) $value)[0];
        $ratio = $monthCount > 0 ? $paidCount / $monthCount : 0;

        return match (true) {
            $paidCount >= $monthCount => self::PALETTE_PAID,
            $ratio >= 0.75 => self::PALETTE_PARTIAL,
            default => self::PALETTE_UNPAID,
        };
    }

    private function applyBlock(Style $style, string $fill, string $text, bool $bold = false, ?int $size = null): void
    {
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($fill);

        $font = $style->getFont()->setBold($bold);
        if ($size !== null) {
            $font->setSize($size);
        }
        $font->getColor()->setARGB($text);

        $style->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
    }

    private function applyFill(Style $style, string $fill): void
    {
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($fill);
    }

    private function paintCell(Worksheet $sheet, string $cell, array $colors): void
    {
        [$fill, $text] = $colors;
        $style = $sheet->getStyle($cell);

        $this->applyFill($style, $fill);
        $style->getFont()->setBold(true)->getColor()->setARGB($text);
        $style->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
    }

    private function monthColumn(int $monthIndex): string
    {
        return Coordinate::stringFromColumnIndex(self::FIXED_LEADING_COLUMNS + $monthIndex);
    }

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

    private function buildRows(): array
    {
        $memorizers = Memorizer::query()
            ->with([
                'group:id,name',
                'guardian:id,phone',
                'payments' => fn ($q) => $q->whereBetween('payment_date', [
                    $this->startDate->copy()->startOfDay(),
                    $this->endDate->copy()->endOfDay(),
                ]),
            ])
            ->orderBy('name')
            ->get();

        return $memorizers->values()->map(function (Memorizer $memorizer, int $index) {
            $cells = [];
            $rowTotal = 0.0;

            $byKey = $memorizer->payments
                ->groupBy(fn ($p) => $p->payment_date->format('Y-m'))
                ->map(fn ($payments) => (float) $payments->sum('amount'));

            foreach ($this->months as $month) {
                if ($memorizer->exempt) {
                    $cells[$month['key']] = ['type' => 'exempt'];
                    continue;
                }

                if ($byKey->has($month['key'])) {
                    $amount = $byKey->get($month['key']);
                    $cells[$month['key']] = ['type' => 'paid', 'amount' => $amount];
                    $rowTotal += $amount;
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
            ];
        })->all();
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
