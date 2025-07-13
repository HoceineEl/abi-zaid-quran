<?php

namespace App\Exports;

use App\Models\Group;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithEvents;

class DailyAttendanceSummaryExport implements FromCollection, WithHeadings, WithStyles, ShouldAutoSize, WithTitle, WithEvents
{
    protected $date;

    public function __construct(string $date)
    {
        $this->date = $date;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        return Group::getDailyAttendanceSummary($this->date);
    }

    public function headings(): array
    {
        return [
            'رقم المجموعة',
            'اسم المجموعة',
            'حاضر',
            'غائب',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1    => ['font' => ['bold' => true]],
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
                foreach ($sheet->getRowIterator() as $row) {
                    if ($row->getRowIndex() === 1) {
                        continue;
                    }

                    $presentCell = 'C' . $row->getRowIndex();
                    $absentCell = 'D' . $row->getRowIndex();

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
