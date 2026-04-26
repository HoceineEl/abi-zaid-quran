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
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class MemorizersYearlyPaymentExport implements FromCollection, WithHeadings, WithTitle, WithEvents
{
    private const STATUS_PAID = 'مدفوع';
    private const STATUS_UNPAID = 'غير مدفوع';
    private const STATUS_EXEMPT = 'معفي';

    private const CHECKBOX_OFF = '☐';
    private const CHECKBOX_ON = '☑';

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
                $row['summary'] = self::STATUS_EXEMPT;
            } else {
                $paidMonths = $memorizer->payments
                    ->map(fn ($payment) => (int) $payment->payment_date->format('n'))
                    ->filter(fn (int $m) => $m <= $this->upToMonth)
                    ->unique();

                for ($month = 1; $month <= $this->upToMonth; $month++) {
                    $row['month_' . $month] = $paidMonths->contains($month)
                        ? self::STATUS_PAID
                        : self::STATUS_UNPAID;
                }
                $row['summary'] = "{$paidMonths->count()} / {$this->upToMonth}";
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

                $summaryCol = $this->summaryColumn();
                $contactedCol = $this->contactedColumn();
                $contactCol = $this->contactColumn();
                $lastColumn = $contactCol;
                $headerRow = 3;
                $firstDataRow = $headerRow + 1;

                $this->renderTitleBlock($sheet, $lastColumn);

                $highestRow = $sheet->getHighestRow();

                $this->styleHeaderRow($sheet, $headerRow, $lastColumn);
                $this->styleDataRows($sheet, $firstDataRow, $highestRow, $summaryCol, $contactedCol, $contactCol);
                $this->setColumnWidths($sheet, $summaryCol, $contactedCol, $contactCol);
                $this->applyContactedCheckbox($sheet, $firstDataRow, $highestRow, $contactedCol);

                $sheet->freezePane('E' . $firstDataRow);

                $sheet->getStyle("A1:{$lastColumn}{$highestRow}")->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);
            },
        ];
    }

    private function renderTitleBlock(Worksheet $sheet, string $lastColumn): void
    {
        $sheet->insertNewRowBefore(1, 2);

        $monthName = self::MONTH_NAMES[$this->upToMonth - 1];

        $sheet->mergeCells("A1:{$lastColumn}1");
        $sheet->setCellValue('A1', "متابعة أداء الواجب · {$this->year} (حتى {$monthName})");
        $sheet->getRowDimension(1)->setRowHeight(32);
        $titleStyle = $sheet->getStyle('A1');
        $titleStyle->getFont()->setBold(true)->setSize(16)->getColor()->setARGB('FFFFFFFF');
        $titleStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF0D5D3F');
        $titleStyle->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);

        $sheet->mergeCells("A2:{$lastColumn}2");
        $sheet->setCellValue('A2', 'تاريخ التقرير: ' . now()->format('Y-m-d') . '  ·  مدفوع · غير مدفوع · معفي');
        $sheet->getRowDimension(2)->setRowHeight(20);
        $subtitleStyle = $sheet->getStyle('A2');
        $subtitleStyle->getFont()->setBold(true)->setSize(11)->getColor()->setARGB('FF8B6914');
        $subtitleStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFAF4E3');
        $subtitleStyle->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
    }

    private function styleHeaderRow(Worksheet $sheet, int $row, string $lastColumn): void
    {
        $range = "A{$row}:{$lastColumn}{$row}";
        $style = $sheet->getStyle($range);
        $style->getFont()->setBold(true)->setSize(11)->getColor()->setARGB('FFFFFFFF');
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF0D5D3F');
        $style->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension($row)->setRowHeight(28);
    }

    private function styleDataRows(Worksheet $sheet, int $firstDataRow, int $highestRow, string $summaryCol, string $contactedCol, string $contactCol): void
    {
        if ($highestRow < $firstDataRow) {
            return;
        }

        for ($row = $firstDataRow; $row <= $highestRow; $row++) {
            $sheet->getRowDimension($row)->setRowHeight(22);

            $stripeColor = ($row - $firstDataRow) % 2 === 0 ? 'FFFFFFFF' : 'FFFAF4E3';
            $sheet->getStyle("A{$row}:D{$row}")->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB($stripeColor);

            $sheet->getStyle("A{$row}:D{$row}")->getAlignment()
                ->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("B{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("B{$row}")->getFont()->setBold(true);
            $sheet->getStyle("C{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("D{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            for ($month = 1; $month <= $this->upToMonth; $month++) {
                $cell = $this->monthColumn($month) . $row;
                $this->paintCell($sheet, $cell, $this->statusColors($sheet->getCell($cell)->getValue()));
            }

            $summaryCell = $summaryCol . $row;
            $this->paintCell($sheet, $summaryCell, $this->summaryColors($sheet->getCell($summaryCell)->getValue()));

            $contactedCell = $contactedCol . $row;
            $contactedStyle = $sheet->getStyle($contactedCell);
            $contactedStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($stripeColor);
            $contactedStyle->getFont()->setSize(14)->getColor()->setARGB('FF0D5D3F');
            $contactedStyle->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER);

            $contactCell = $contactCol . $row;
            $sheet->getStyle($contactCell)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB($stripeColor);
            $sheet->getStyle($contactCell)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_RIGHT)
                ->setVertical(Alignment::VERTICAL_CENTER)
                ->setWrapText(true);
        }
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

    private function setColumnWidths(Worksheet $sheet, string $summaryCol, string $contactedCol, string $contactCol): void
    {
        $sheet->getColumnDimension('A')->setWidth(5);
        $sheet->getColumnDimension('B')->setWidth(28);
        $sheet->getColumnDimension('C')->setWidth(18);
        $sheet->getColumnDimension('D')->setWidth(22);

        for ($month = 1; $month <= $this->upToMonth; $month++) {
            $sheet->getColumnDimension($this->monthColumn($month))->setWidth(10);
        }

        $sheet->getColumnDimension($summaryCol)->setWidth(12);
        $sheet->getColumnDimension($contactedCol)->setWidth(8);
        $sheet->getColumnDimension($contactCol)->setWidth(40);
    }

    /**
     * @return array{0: string, 1: string} [fillARGB, textARGB]
     */
    private function statusColors(?string $value): array
    {
        return match ($value) {
            self::STATUS_PAID => ['FFC6EFCE', 'FF006100'],
            self::STATUS_UNPAID => ['FFFFC7CE', 'FF9C0006'],
            self::STATUS_EXEMPT => ['FFDDEBF7', 'FF0066CC'],
            default => ['FFF2F2F2', 'FF7F7F7F'],
        };
    }

    /**
     * @return array{0: string, 1: string} [fillARGB, textARGB]
     */
    private function summaryColors(?string $value): array
    {
        if ($value === self::STATUS_EXEMPT) {
            return ['FFDDEBF7', 'FF0066CC'];
        }

        $paidCount = (int) explode('/', (string) $value)[0];
        $ratio = $this->upToMonth > 0 ? $paidCount / $this->upToMonth : 0;

        return match (true) {
            $paidCount >= $this->upToMonth => ['FFC6EFCE', 'FF006100'],
            $ratio >= 0.75 => ['FFFFF2CC', 'FF8B6914'],
            default => ['FFFFC7CE', 'FF9C0006'],
        };
    }

    /**
     * @param  array{0: string, 1: string}  $colors
     */
    private function paintCell(Worksheet $sheet, string $cell, array $colors): void
    {
        [$fill, $text] = $colors;

        $style = $sheet->getStyle($cell);
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($fill);
        $style->getFont()->setBold(true)->getColor()->setARGB($text);
        $style->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
    }

    private function monthColumn(int $month): string
    {
        return Coordinate::stringFromColumnIndex(self::FIXED_LEADING_COLUMNS + $month);
    }

    private function summaryColumn(): string
    {
        return Coordinate::stringFromColumnIndex(self::FIXED_LEADING_COLUMNS + $this->upToMonth + 1);
    }

    private function contactedColumn(): string
    {
        return Coordinate::stringFromColumnIndex(self::FIXED_LEADING_COLUMNS + $this->upToMonth + 2);
    }

    private function contactColumn(): string
    {
        return Coordinate::stringFromColumnIndex(self::FIXED_LEADING_COLUMNS + $this->upToMonth + 3);
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
