<?php

namespace App\Exports;

use App\Models\Group;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Style;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DailyAttendanceSummaryExport implements FromCollection, WithHeadings, WithTitle, WithEvents
{
    private const COLOR_WHITE = 'FFFFFFFF';
    private const COLOR_EMERALD = 'FF0D5D3F';
    private const COLOR_CREAM = 'FFFAF4E3';
    private const COLOR_GOLD = 'FFB8860B';
    private const COLOR_GOLD_TEXT = 'FF8B6914';
    private const COLOR_INK = 'FF2D1F0F';
    private const COLOR_STRIPE = 'FFFDF8E8';

    private const PALETTE_TOTAL = ['FFBDD7EE', 'FF1F4E79'];
    private const PALETTE_PRESENT = ['FFC6EFCE', 'FF006100'];
    private const PALETTE_ABSENT = ['FFFFC7CE', 'FF9C0006'];
    private const PALETTE_REASON = ['FFFFEB9C', 'FF9C6500'];
    private const PALETTE_UNSET = ['FFE5E5E5', 'FF595959'];

    /**
     * Ordered metric columns: column letter => [heading, source key, palette].
     * Drives headings, data mapping, totals, and per-cell styling.
     */
    private const METRIC_COLUMNS = [
        'B' => ['إجمالي الطلاب', 'total_students', self::PALETTE_TOTAL],
        'C' => ['حاضر', 'present', self::PALETTE_PRESENT],
        'D' => ['غائب', 'absent', self::PALETTE_ABSENT],
        'E' => ['غائب بعذر', 'absent_with_reason', self::PALETTE_REASON],
        'F' => ['لم يحدد', 'not_specified', self::PALETTE_UNSET],
    ];

    private const NAME_HEADING = 'اسم المجموعة';

    private const TITLE_ROW = 1;
    private const SUBTITLE_ROW = 2;
    private const HEADER_ROW = 3;
    private const FIRST_DATA_ROW = 4;
    private const LAST_COLUMN = 'F';

    private ?Collection $rows = null;

    public function __construct(private readonly string $date, private readonly ?int $userId = null)
    {
    }

    public function collection()
    {
        return $this->rows()->map(fn (array $row) => [
            'name' => $row['name'],
            'total_students' => $row['total_students'],
            'present' => $this->formatMetric($row['present'], $row['total_students']),
            'absent' => $this->formatMetric($row['absent'], $row['total_students']),
            'absent_with_reason' => $this->formatMetric($row['absent_with_reason'], $row['total_students']),
            'not_specified' => $this->formatMetric($row['not_specified'], $row['total_students']),
        ])->values();
    }

    public function headings(): array
    {
        return [self::NAME_HEADING, ...array_column(self::METRIC_COLUMNS, 0)];
    }

    public function title(): string
    {
        return 'ملخص الحضور اليومي';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->setRightToLeft(true);

                $sheet->insertNewRowBefore(1, 2);

                $this->renderTitleBlock($sheet);
                $this->styleHeaderRow($sheet);

                $highestRow = $sheet->getHighestRow();

                $this->styleDataRows($sheet, $highestRow);
                $totalsRow = $this->appendTotalsRow($sheet, $highestRow);
                $this->setColumnWidths($sheet);

                $sheet->freezePane('A' . self::FIRST_DATA_ROW);

                $finalRow = $totalsRow ?: $highestRow;
                $sheet->getStyle('A' . self::HEADER_ROW . ':' . self::LAST_COLUMN . $finalRow)
                    ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            },
        ];
    }

    private function renderTitleBlock(Worksheet $sheet): void
    {
        $arabicDate = Carbon::parse($this->date)->locale('ar')->translatedFormat('l، j F Y');

        $this->renderBannerRow($sheet, self::TITLE_ROW, 'تقرير ملخص الحضور اليومي', 34, self::COLOR_EMERALD, self::COLOR_WHITE, 17);
        $this->renderBannerRow($sheet, self::SUBTITLE_ROW, 'التاريخ: ' . $arabicDate, 22, self::COLOR_CREAM, self::COLOR_GOLD_TEXT, 12);
    }

    private function renderBannerRow(Worksheet $sheet, int $row, string $value, int $height, string $fill, string $text, int $size): void
    {
        $sheet->mergeCells("A{$row}:" . self::LAST_COLUMN . $row);
        $sheet->setCellValue("A{$row}", $value);
        $sheet->getRowDimension($row)->setRowHeight($height);
        $this->applyBlock($sheet->getStyle("A{$row}"), $fill, $text, bold: true, size: $size);
    }

    private function styleHeaderRow(Worksheet $sheet): void
    {
        $row = self::HEADER_ROW;
        $this->applyBlock(
            $sheet->getStyle("A{$row}:" . self::LAST_COLUMN . $row),
            self::COLOR_EMERALD,
            self::COLOR_WHITE,
            bold: true,
            size: 12,
        );
        $sheet->getRowDimension($row)->setRowHeight(28);
    }

    private function styleDataRows(Worksheet $sheet, int $highestRow): void
    {
        if ($highestRow < self::FIRST_DATA_ROW) {
            return;
        }

        for ($row = self::FIRST_DATA_ROW; $row <= $highestRow; $row++) {
            $sheet->getRowDimension($row)->setRowHeight(24);

            $stripe = ($row - self::FIRST_DATA_ROW) % 2 === 0 ? self::COLOR_WHITE : self::COLOR_STRIPE;
            $this->styleNameCell($sheet->getStyle("A{$row}"), $stripe);

            foreach (self::METRIC_COLUMNS as $column => [, , $palette]) {
                $this->paintMetric($sheet->getStyle("{$column}{$row}"), $palette);
            }
        }
    }

    private function appendTotalsRow(Worksheet $sheet, int $highestRow): int
    {
        if ($highestRow < self::FIRST_DATA_ROW) {
            return 0;
        }

        $totals = $this->collectTotals();
        $row = $highestRow + 1;

        $sheet->getRowDimension($row)->setRowHeight(30);
        $sheet->setCellValue("A{$row}", 'الإجمالي');
        $sheet->setCellValue("B{$row}", $totals['total_students']);

        foreach (self::METRIC_COLUMNS as $column => [, $key, ]) {
            if ($column === 'B') {
                continue; // total_students is shown as a raw number, not a percentage
            }
            $sheet->setCellValue("{$column}{$row}", $this->formatMetric($totals[$key], $totals['total_students']));
        }

        $rowStyle = $sheet->getStyle("A{$row}:" . self::LAST_COLUMN . $row);
        $this->applyBlock($rowStyle, self::COLOR_EMERALD, self::COLOR_WHITE, bold: true, size: 12);
        $rowStyle->getBorders()->getTop()
            ->setBorderStyle(Border::BORDER_MEDIUM)
            ->getColor()->setARGB(self::COLOR_GOLD);

        $sheet->getStyle("A{$row}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_RIGHT)
            ->setIndent(1);

        return $row;
    }

    private function setColumnWidths(Worksheet $sheet): void
    {
        $sheet->getColumnDimension('A')->setWidth(34);

        foreach (array_keys(self::METRIC_COLUMNS) as $column) {
            $sheet->getColumnDimension($column)->setWidth(16);
        }
    }

    private function styleNameCell(Style $style, string $fill): void
    {
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($fill);
        $style->getFont()->setBold(true)->setSize(11)->getColor()->setARGB(self::COLOR_INK);
        $style->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_RIGHT)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setIndent(1);
    }

    /**
     * @param  array{0: string, 1: string}  $palette
     */
    private function paintMetric(Style $style, array $palette): void
    {
        [$fill, $text] = $palette;
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($fill);
        $style->getFont()->setBold(true)->setSize(11)->getColor()->setARGB($text);
        $style->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
    }

    private function applyBlock(Style $style, string $fill, string $text, bool $bold = false, int $size = 11): void
    {
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($fill);
        $style->getFont()->setBold($bold)->setSize($size)->getColor()->setARGB($text);
        $style->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
    }

    private function formatMetric(int $count, int $total): string
    {
        if ($total <= 0) {
            return $count > 0 ? (string) $count : '—';
        }

        $percentage = (int) round($count / $total * 100);

        return "{$count} ({$percentage}%)";
    }

    /**
     * @return array{total_students: int, present: int, absent: int, absent_with_reason: int, not_specified: int}
     */
    private function collectTotals(): array
    {
        $rows = $this->rows();

        return [
            'total_students' => (int) $rows->sum('total_students'),
            'present' => (int) $rows->sum('present'),
            'absent' => (int) $rows->sum('absent'),
            'absent_with_reason' => (int) $rows->sum('absent_with_reason'),
            'not_specified' => (int) $rows->sum('not_specified'),
        ];
    }

    private function rows(): Collection
    {
        return $this->rows ??= Group::getDailyAttendanceSummary($this->date, $this->userId);
    }
}
