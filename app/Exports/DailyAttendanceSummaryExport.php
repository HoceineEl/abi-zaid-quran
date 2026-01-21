<?php

namespace App\Exports;

use App\Models\Group;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithEvents;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class DailyAttendanceSummaryExport implements FromCollection, WithHeadings, ShouldAutoSize, WithTitle, WithEvents
{
    protected $date;
    protected $userId;

    public function __construct(string $date, $userId = null)
    {
        $this->date = $date;
        $this->userId = $userId;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        return Group::getDailyAttendanceSummary($this->date, $this->userId)->map(function ($row) {
            return [
                'name' => $row['name'],
                'total_students' => $row['total_students'],
                'present' => $row['present'] ?: '-',
                'absent' => $row['absent'] ?: '-',
                'absent_with_reason' => $row['absent_with_reason'] ?: '-',
                'not_specified' => $row['not_specified'] ?: '-',
            ];
        });
    }

    public function headings(): array
    {
        return [
            'اسم المجموعة',
            'إجمالي الطلاب',
            'حاضر',
            'غائب',
            'غائب بعذر',
            'لم يحدد',
        ];
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

                // Set right-to-left direction for Arabic text
                $sheet->setRightToLeft(true);

                // Add translated date as a heading using the selected date
                $selectedDate = Carbon::parse($this->date)->locale('ar')->translatedFormat('l, j F Y');
                $sheet->insertNewRowBefore(1, 1);
                $sheet->mergeCells('A1:F1');
                $sheet->setCellValue('A1', 'تقرير الحضور ليوم: ' . $selectedDate);

                // Style the heading
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Style the original headings row (which is now row 2)
                $sheet->getStyle('A2:F2')->getFont()->setBold(true);

                if ($sheet->getHighestRow() < 3) {
                    return;
                }

                // Color the data rows
                foreach ($sheet->getRowIterator(3) as $row) {
                    $rowIndex = $row->getRowIndex();
                    $totalStudentsCell = 'B' . $rowIndex;
                    $presentCell = 'C' . $rowIndex;
                    $absentCell = 'D' . $rowIndex;
                    $absentWithReasonCell = 'E' . $rowIndex;
                    $notSpecifiedCell = 'F' . $rowIndex;

                    // Color for total students (blue)
                    $sheet->getStyle($totalStudentsCell)->getFill()
                        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                        ->getStartColor()->setARGB('BDD7EE');
                    $sheet->getStyle($totalStudentsCell)->getFont()->getColor()->setARGB('1F4E79');

                    // Color for present (green)
                    $sheet->getStyle($presentCell)->getFill()
                        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                        ->getStartColor()->setARGB('C6EFCE');
                    $sheet->getStyle($presentCell)->getFont()->getColor()->setARGB('006100');

                    // Color for absent without reason (red)
                    $sheet->getStyle($absentCell)->getFill()
                        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                        ->getStartColor()->setARGB('FFC7CE');
                    $sheet->getStyle($absentCell)->getFont()->getColor()->setARGB('9C0006');

                    // Color for absent with reason (yellow/amber)
                    $sheet->getStyle($absentWithReasonCell)->getFill()
                        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                        ->getStartColor()->setARGB('FFEB9C');
                    $sheet->getStyle($absentWithReasonCell)->getFont()->getColor()->setARGB('9C6500');

                    // Color for not specified (gray)
                    $sheet->getStyle($notSpecifiedCell)->getFill()
                        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                        ->getStartColor()->setARGB('D9D9D9');
                    $sheet->getStyle($notSpecifiedCell)->getFont()->getColor()->setARGB('595959');
                }
            },
        ];
    }
}
