<?php

namespace App\Exports;

use App\Models\Group;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class GroupStudentsSheetExport implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings, WithTitle
{
    public function __construct(
        protected Group $group,
    ) {}

    public function collection(): Collection
    {
        return $this->group->students()
            ->orderBy('order_no')
            ->get()
            ->map(function ($student, $index) {
                return [
                    'order' => $index + 1,
                    'name' => $student->name,
                    'phone' => $this->formatPhone($student->phone),
                    'sex' => $student->sex === 'male' ? 'ذكر' : 'أنثى',
                    'city' => $student->city ?: '-',
                    'test_1' => '',
                    'test_2' => '',
                    'test_3' => '',
                ];
            });
    }

    public function headings(): array
    {
        return [
            '#',
            'اسم الطالب',
            'رقم الهاتف',
            'الجنس',
            'المدينة',
            'نتيجة الاختبار 1',
            'نتيجة الاختبار 2',
            'نتيجة الاختبار 3',
        ];
    }

    public function title(): string
    {
        return mb_substr($this->group->name, 0, 31);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->setRightToLeft(true);

                $totalStudents = $this->group->students()->count();
                $lastCol = 'H';

                // Insert 3 header rows
                $sheet->insertNewRowBefore(1, 3);

                // Row 1: Group name title
                $sheet->mergeCells("A1:{$lastCol}1");
                $sheet->setCellValue('A1', $this->group->name);
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16)->getColor()->setARGB('FFFFFF');
                $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle('A1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('2F5496');
                $sheet->getRowDimension(1)->setRowHeight(35);

                // Row 2: Group info
                $type = match ($this->group->type) {
                    'two_lines' => 'سطران',
                    'half_page' => 'نصف صفحة',
                    default => $this->group->type,
                };
                $managers = $this->group->managers->pluck('name')->join('، ');
                $info = "نوع الحفظ: {$type}  |  عدد الطلاب: {$totalStudents}";
                if ($managers) {
                    $info .= "  |  المشرفون: {$managers}";
                }

                $sheet->mergeCells("A2:{$lastCol}2");
                $sheet->setCellValue('A2', $info);
                $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(11)->getColor()->setARGB('2F5496');
                $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle('A2')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('D6E4F0');
                $sheet->getRowDimension(2)->setRowHeight(25);

                // Row 3: Export date
                $sheet->mergeCells("A3:{$lastCol}3");
                $sheet->setCellValue('A3', 'تاريخ التصدير: ' . now()->locale('ar')->translatedFormat('l, j F Y'));
                $sheet->getStyle('A3')->getFont()->setSize(9)->setItalic(true)->getColor()->setARGB('666666');
                $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('A3')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('F2F2F2');

                // Style headings row (now row 4)
                $sheet->getStyle("A4:{$lastCol}4")->getFont()->setBold(true)->setSize(13)->getColor()->setARGB('FFFFFF');
                $sheet->getStyle("A4:{$lastCol}4")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('4472C4');
                $sheet->getStyle("A4:{$lastCol}4")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getRowDimension(4)->setRowHeight(28);

                // Style data rows
                $highestRow = $sheet->getHighestRow();
                if ($highestRow < 5) {
                    return;
                }

                // Bold font and larger size for all data rows
                $sheet->getStyle("A5:{$lastCol}{$highestRow}")->getFont()->setBold(true)->setSize(12);

                for ($rowIndex = 5; $rowIndex <= $highestRow; $rowIndex++) {
                    // Alternate row colors
                    $fillColor = $rowIndex % 2 === 1 ? 'F5F8FC' : 'FFFFFF';
                    $sheet->getStyle("A{$rowIndex}:{$lastCol}{$rowIndex}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($fillColor);

                    // Center align # column
                    $sheet->getStyle("A{$rowIndex}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }

                // Increase row height for data rows
                for ($rowIndex = 5; $rowIndex <= $highestRow; $rowIndex++) {
                    $sheet->getRowDimension($rowIndex)->setRowHeight(24);
                }

                // Style test result columns (F, G, H) with light yellow background to indicate fillable
                $sheet->getStyle("F5:H{$highestRow}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFDE7');
                $sheet->getStyle("F5:H{$highestRow}")->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('E0C85A');

                // Borders for the entire table (rows 4 to end)
                $sheet->getStyle("A4:{$lastCol}{$highestRow}")->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('B4C6E7');

                // Thicker border around the whole table
                $sheet->getStyle("A4:{$lastCol}{$highestRow}")->getBorders()->getOutline()
                    ->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setARGB('2F5496');

                // Borders for header rows
                $sheet->getStyle("A1:{$lastCol}3")->getBorders()->getOutline()
                    ->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setARGB('2F5496');

                // Set column widths
                $sheet->getColumnDimension('A')->setWidth(6);
                $sheet->getColumnDimension('B')->setAutoSize(true);
                $sheet->getColumnDimension('C')->setWidth(16);
                $sheet->getColumnDimension('D')->setWidth(8);
                $sheet->getColumnDimension('E')->setAutoSize(true);
                $sheet->getColumnDimension('F')->setWidth(18);
                $sheet->getColumnDimension('G')->setWidth(18);
                $sheet->getColumnDimension('H')->setWidth(18);

                // Center align specific columns for all data rows
                $sheet->getStyle("D5:D{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("F5:H{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Summary row
                $summaryRow = $highestRow + 1;
                $sheet->mergeCells("A{$summaryRow}:B{$summaryRow}");
                $sheet->setCellValue("A{$summaryRow}", "إجمالي الطلاب: {$totalStudents}");
                $sheet->getStyle("A{$summaryRow}:{$lastCol}{$summaryRow}")->getFont()->setBold(true)->setSize(11);
                $sheet->getStyle("A{$summaryRow}:{$lastCol}{$summaryRow}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('D6E4F0');
                $sheet->getStyle("A{$summaryRow}:{$lastCol}{$summaryRow}")->getBorders()->getOutline()
                    ->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setARGB('2F5496');
            },
        ];
    }

    private function formatPhone(?string $phone): string
    {
        if (empty($phone)) {
            return 'غير محدد';
        }

        try {
            return phone($phone, 'MA')->formatNational();
        } catch (\Exception) {
            return $phone;
        }
    }
}
