<?php

namespace App\Exports;

use App\Models\Memorizer;
use Maatwebsite\Excel\Concerns\FromCollection;
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

class MemorizersYearlyPaymentExport implements FromCollection, WithHeadings, WithTitle, WithEvents
{
    private const STATUS_UNPAID = 'غير مدفوع';
    private const STATUS_EXEMPT = 'معفي';

    private const CHECKBOX_OFF = '☐';
    private const CHECKBOX_ON = '☑';

    private const PAID_FORMAT = '"مدفوع · "#,##0';
    private const CURRENCY_FORMAT = '#,##0 "د.م"';

    // Brand colors
    private const COLOR_WHITE = 'FFFFFFFF';
    private const COLOR_EMERALD = 'FF0D5D3F';
    private const COLOR_GOLD = 'FFB8860B';
    private const COLOR_CREAM = 'FFFAF4E3';
    private const COLOR_GOLD_TEXT = 'FF8B6914';

    // Status palette: [fill, text]
    private const PALETTE_PAID = ['FFC6EFCE', 'FF006100'];
    private const PALETTE_UNPAID = ['FFFFC7CE', 'FF9C0006'];
    private const PALETTE_EXEMPT = ['FFDDEBF7', 'FF0066CC'];
    private const PALETTE_NEUTRAL = ['FFF2F2F2', 'FF7F7F7F'];
    private const PALETTE_PARTIAL = ['FFFFF2CC', 'FF8B6914'];

    private const MONTH_NAMES = [
        'يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو',
        'يوليو', 'غشت', 'شتنبر', 'أكتوبر', 'نونبر', 'دجنبر',
    ];

    private const FIXED_LEADING_COLUMNS = 4;

    private readonly int $upToMonth;

    public function __construct(private readonly int $year, int $upToMonth)
    {
        $this->upToMonth = max(1, min(12, $upToMonth));
    }

    public function collection()
    {
        $memorizers = Memorizer::query()
            ->with([
                'group:id,name',
                'guardian:id,phone',
                'payments' => fn ($q) => $q->whereYear('payment_date', $this->year),
            ])
            ->orderBy('name')
            ->get();

        return $memorizers->map(function (Memorizer $memorizer, int $index) {
            $row = [
                'index' => $index + 1,
                'name' => $memorizer->name,
                'phone' => $this->formatPhone($memorizer->displayPhone),
                'group' => $memorizer->group?->name ?? '—',
            ];

            if ($memorizer->exempt) {
                for ($month = 1; $month <= $this->upToMonth; $month++) {
                    $row['month_' . $month] = self::STATUS_EXEMPT;
                }
                $row['row_total'] = self::STATUS_EXEMPT;
                $row['summary'] = self::STATUS_EXEMPT;
            } else {
                $paymentsByMonth = $memorizer->payments
                    ->filter(fn ($p) => (int) $p->payment_date->format('n') <= $this->upToMonth)
                    ->groupBy(fn ($p) => (int) $p->payment_date->format('n'))
                    ->map(fn ($payments) => (float) $payments->sum('amount'));

                $rowTotal = 0.0;
                $paidCount = 0;

                for ($month = 1; $month <= $this->upToMonth; $month++) {
                    if ($paymentsByMonth->has($month)) {
                        $amount = $paymentsByMonth->get($month);
                        $row['month_' . $month] = $amount;
                        $rowTotal += $amount;
                        $paidCount++;
                    } else {
                        $row['month_' . $month] = self::STATUS_UNPAID;
                    }
                }

                $row['row_total'] = $rowTotal;
                $row['summary'] = "{$paidCount} / {$this->upToMonth}";
            }

            $row['contacted'] = self::CHECKBOX_OFF;
            $row['contact_result'] = '';

            return $row;
        });
    }

    public function headings(): array
    {
        return [
            '#',
            'الاسم',
            'رقم الهاتف',
            'المجموعة',
            ...array_slice(self::MONTH_NAMES, 0, $this->upToMonth),
            'المجموع (د.م)',
            'الملخص',
            'تم التواصل',
            'نتيجة التواصل',
        ];
    }

    public function title(): string
    {
        $monthName = self::MONTH_NAMES[$this->upToMonth - 1];

        return "أداء الواجب {$this->year} (حتى {$monthName})";
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

                $sheet->freezePane('E' . $firstDataRow);

                $finalRow = $totalsRow ?: $highestRow;
                $sheet->getStyle("A{$headerRow}:{$columns['contact']}{$finalRow}")
                    ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            },
        ];
    }

    /**
     * Build the column-letter layout for the sheet.
     *
     * @return array{row_total: string, summary: string, contacted: string, contact: string}
     */
    private function columnLayout(): array
    {
        $base = self::FIXED_LEADING_COLUMNS + $this->upToMonth;

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

        $monthName = self::MONTH_NAMES[$this->upToMonth - 1];

        $sheet->mergeCells("A1:{$lastColumn}1");
        $sheet->setCellValue('A1', "متابعة أداء الواجب · {$this->year} (حتى {$monthName})");
        $sheet->getRowDimension(1)->setRowHeight(32);
        $this->applyBlock($sheet->getStyle('A1'), self::COLOR_EMERALD, self::COLOR_WHITE, bold: true, size: 16);

        $sheet->mergeCells("A2:{$lastColumn}2");
        $sheet->setCellValue('A2', 'تاريخ التقرير: ' . now()->format('Y-m-d') . '  ·  مدفوع · غير مدفوع · معفي');
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

    /**
     * @param  array{row_total: string, summary: string, contacted: string, contact: string}  $columns
     */
    private function styleDataRows(Worksheet $sheet, int $firstDataRow, int $highestRow, array $columns): void
    {
        if ($highestRow < $firstDataRow) {
            return;
        }

        for ($row = $firstDataRow; $row <= $highestRow; $row++) {
            $sheet->getRowDimension($row)->setRowHeight(22);

            $stripeColor = ($row - $firstDataRow) % 2 === 0 ? self::COLOR_WHITE : self::COLOR_CREAM;

            $leadingRange = "A{$row}:D{$row}";
            $this->applyFill($sheet->getStyle($leadingRange), $stripeColor);
            $sheet->getStyle($leadingRange)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("B{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("B{$row}")->getFont()->setBold(true);
            $sheet->getStyle("C{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("D{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            for ($month = 1; $month <= $this->upToMonth; $month++) {
                $cell = $this->monthColumn($month) . $row;
                $value = $sheet->getCell($cell)->getValue();
                $this->paintCell($sheet, $cell, $this->statusColors($value));

                if (is_numeric($value)) {
                    $sheet->getStyle($cell)->getNumberFormat()->setFormatCode(self::PAID_FORMAT);
                }
            }

            $rowTotalCell = $columns['row_total'] . $row;
            $rowTotalValue = $sheet->getCell($rowTotalCell)->getValue();
            $this->paintCell($sheet, $rowTotalCell, $this->rowTotalColors($rowTotalValue));
            if (is_numeric($rowTotalValue)) {
                $sheet->getStyle($rowTotalCell)->getNumberFormat()->setFormatCode(self::CURRENCY_FORMAT);
            }

            $summaryCell = $columns['summary'] . $row;
            $this->paintCell($sheet, $summaryCell, $this->summaryColors($sheet->getCell($summaryCell)->getValue()));

            $contactedCell = $columns['contacted'] . $row;
            $contactedStyle = $sheet->getStyle($contactedCell);
            $this->applyFill($contactedStyle, $stripeColor);
            $contactedStyle->getFont()->setSize(14)->getColor()->setARGB(self::COLOR_EMERALD);
            $contactedStyle->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER);

            $contactCell = $columns['contact'] . $row;
            $contactStyle = $sheet->getStyle($contactCell);
            $this->applyFill($contactStyle, $stripeColor);
            $contactStyle->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_RIGHT)
                ->setVertical(Alignment::VERTICAL_CENTER)
                ->setWrapText(true);
        }
    }

    /**
     * @param  array{row_total: string, summary: string, contacted: string, contact: string}  $columns
     */
    private function appendTotalsRow(Worksheet $sheet, int $firstDataRow, int $highestRow, array $columns): int
    {
        if ($highestRow < $firstDataRow) {
            return 0;
        }

        $totalsRow = $highestRow + 1;
        $rowTotalCol = $columns['row_total'];
        $contactCol = $columns['contact'];

        $sheet->getRowDimension($totalsRow)->setRowHeight(30);

        $sheet->mergeCells("A{$totalsRow}:D{$totalsRow}");
        $sheet->setCellValue("A{$totalsRow}", 'الإجمالي (د.م)');

        for ($month = 1; $month <= $this->upToMonth; $month++) {
            $col = $this->monthColumn($month);
            $sheet->setCellValue(
                "{$col}{$totalsRow}",
                "=SUM({$col}{$firstDataRow}:{$col}{$highestRow})"
            );
        }

        $sheet->setCellValue(
            "{$rowTotalCol}{$totalsRow}",
            "=SUM({$rowTotalCol}{$firstDataRow}:{$rowTotalCol}{$highestRow})"
        );

        $rowStyle = $sheet->getStyle("A{$totalsRow}:{$contactCol}{$totalsRow}");
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
            $validation = $sheet->getCell($contactedCol . $row)->getDataValidation();
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setErrorStyle(DataValidation::STYLE_INFORMATION);
            $validation->setAllowBlank(false);
            $validation->setShowInputMessage(true);
            $validation->setShowErrorMessage(true);
            $validation->setShowDropDown(true);
            $validation->setPromptTitle('تم التواصل');
            $validation->setPrompt('اختر ☑ إذا تم التواصل مع الطالب أو وليّ أمره.');
            $validation->setFormula1('"' . self::CHECKBOX_OFF . ',' . self::CHECKBOX_ON . '"');
        }
    }

    /**
     * @param  array{row_total: string, summary: string, contacted: string, contact: string}  $columns
     */
    private function setColumnWidths(Worksheet $sheet, array $columns): void
    {
        $sheet->getColumnDimension('A')->setWidth(5);
        $sheet->getColumnDimension('B')->setWidth(28);
        $sheet->getColumnDimension('C')->setWidth(18);
        $sheet->getColumnDimension('D')->setWidth(22);

        for ($month = 1; $month <= $this->upToMonth; $month++) {
            $sheet->getColumnDimension($this->monthColumn($month))->setWidth(16);
        }

        $sheet->getColumnDimension($columns['row_total'])->setWidth(16);
        $sheet->getColumnDimension($columns['summary'])->setWidth(12);
        $sheet->getColumnDimension($columns['contacted'])->setWidth(8);
        $sheet->getColumnDimension($columns['contact'])->setWidth(40);
    }

    /**
     * @return array{0: string, 1: string} [fillARGB, textARGB]
     */
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

    /**
     * @return array{0: string, 1: string} [fillARGB, textARGB]
     */
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

    /**
     * @return array{0: string, 1: string} [fillARGB, textARGB]
     */
    private function summaryColors(?string $value): array
    {
        if ($value === self::STATUS_EXEMPT) {
            return self::PALETTE_EXEMPT;
        }

        $paidCount = (int) explode('/', (string) $value)[0];
        $ratio = $this->upToMonth > 0 ? $paidCount / $this->upToMonth : 0;

        return match (true) {
            $paidCount >= $this->upToMonth => self::PALETTE_PAID,
            $ratio >= 0.75 => self::PALETTE_PARTIAL,
            default => self::PALETTE_UNPAID,
        };
    }

    /**
     * Apply a solid fill, font color, optional bold/size, and centered alignment to a style block.
     */
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

    /**
     * @param  array{0: string, 1: string}  $colors
     */
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

    private function monthColumn(int $month): string
    {
        return Coordinate::stringFromColumnIndex(self::FIXED_LEADING_COLUMNS + $month);
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
