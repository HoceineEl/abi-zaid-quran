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
        // Remove group number from the mapped data
        return Group::getDailyAttendanceSummary($this->date, $this->userId)->map(function ($row) {
            return [
                'name' => $row['name'],
                'present' => $row['present'],
                'absent' => $row['absent'],
            ];
        });
    }

    public function headings(): array
    {
        return [
            'اسم المجموعة',
            'حاضر',
            'غائب',
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
                $sheet->mergeCells('A1:C1');
                $sheet->setCellValue('A1', 'تقرير الحضور ليوم: ' . $selectedDate);

                // Style the heading
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Style the original headings row (which is now row 2)
                $sheet->getStyle('A2:C2')->getFont()->setBold(true);

                if ($sheet->getHighestRow() < 3) {
                    return;
                }
                // Color the data rows
                foreach ($sheet->getRowIterator(3) as $row) {
                    $presentCell = 'B' . $row->getRowIndex();
                    $absentCell = 'C' . $row->getRowIndex();

                    // Color for present
                    $sheet->getStyle($presentCell)->getFill()
                        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                        ->getStartColor()->setARGB('C6EFCE');
                    $sheet->getStyle($presentCell)->getFont()->getColor()->setARGB('006100');

                    // Color for absent
                    $sheet->getStyle($absentCell)->getFill()
                        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                        ->getStartColor()->setARGB('FFC7CE');
                    $sheet->getStyle($absentCell)->getFont()->getColor()->setARGB('9C0006');
                }
            },
        ];
    }
}
