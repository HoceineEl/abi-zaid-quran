<?php

namespace App\Exports;

use App\Enums\DisconnectionStatus;
use App\Enums\MessageResponseStatus;
use App\Models\StudentDisconnection;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithEvents;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class StudentDisconnectionExport implements FromCollection, WithHeadings, ShouldAutoSize, WithTitle, WithEvents
{
    protected $dateRange;
    protected $startDate;
    protected $endDate;

    public function __construct(string $dateRange = 'جميع الطلاب المنقطعين', ?string $startDate = null, ?string $endDate = null)
    {
        $this->dateRange = $dateRange;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function collection()
    {
        $query = StudentDisconnection::with(['student', 'group']);

        if ($this->startDate && $this->endDate) {
            $query->whereBetween('created_at', [$this->startDate . ' 00:00:00', $this->endDate . ' 23:59:59']);
        }

        return $query->orderBy('created_at', 'desc')->get()
            ->map(function ($disconnection) {
                $daysSinceLastPresent = $disconnection->student->getDaysSinceLastPresentAttribute();
                $disconnectionDuration = $daysSinceLastPresent === null ? 'غير محدد' : $daysSinceLastPresent . ' يوم';

                $messageResponse = match ($disconnection->message_response) {
                    MessageResponseStatus::Yes => 'نعم',
                    MessageResponseStatus::No => 'لا',
                    MessageResponseStatus::NotContacted => 'لم يتم التواصل',
                    null => 'لم يتم التواصل',
                    default => 'لم يتم التواصل',
                };

                $status = match ($disconnection->status) {
                    DisconnectionStatus::Disconnected => 'منقطع',
                    DisconnectionStatus::Contacted => 'تم الاتصال',
                    DisconnectionStatus::Responded => 'تم التواصل',
                    null => 'غير محدد',
                    default => 'غير محدد',
                };

                return [
                    'student_order' => $disconnection->student->order_no ?? '',
                    'student_name' => $disconnection->student->name,
                    'message_response' => $messageResponse,
                    'group_name' => $disconnection->group->name,
                    'disconnection_date' => $disconnection->disconnection_date,
                    'disconnection_duration' => $disconnectionDuration,
                    'contact_date' => $disconnection->contact_date ? Carbon::parse($disconnection->contact_date)->format('Y-m-d') : 'لم يتم التواصل',
                    'status' => $status,
                    'notes' => $disconnection->notes ?? '',
                    'created_at' => Carbon::parse($disconnection->created_at)->format('Y-m-d H:i'),
                ];
            });
    }

    public function headings(): array
    {
        return [
            'الترتيب',
            'اسم الطالب',
            'تفاعل مع الرسالة',
            'المجموعة',
            'تاريخ الانقطاع',
            'مدة الانقطاع',
            'تاريخ التواصل',
            'الحالة',
            'ملاحظات',
            'تاريخ الإنشاء',
        ];
    }

    public function title(): string
    {
        return 'قائمة الطلاب المنقطعين';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Set right-to-left direction for Arabic text
                $sheet->setRightToLeft(true);

                // Add title and date range as headings
                $sheet->insertNewRowBefore(1, 2);
                $sheet->mergeCells('A1:J1');
                $sheet->setCellValue('A1', 'تقرير الطلاب المنقطعين');

                $sheet->mergeCells('A2:J2');
                $sheet->setCellValue('A2', 'النطاق الزمني: ' . $this->dateRange);

                // Style the main title
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
                $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('A1')->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('4472C4');

                // Style the subtitle
                $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);
                $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('A2')->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('D9E1F2');

                // Style the headings row (which is now row 3)
                $sheet->getStyle('A3:J3')->getFont()->setBold(true);
                $sheet->getStyle('A3:J3')->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('E7E6E6');

                // Style the data rows
                if ($sheet->getHighestRow() < 4) {
                    return;
                }

                foreach ($sheet->getRowIterator(4) as $row) {
                    $rowIndex = $row->getRowIndex();

                    // Alternate row colors for better readability
                    $fillColor = $rowIndex % 2 == 0 ? 'F9F9F9' : 'FFFFFF';
                    $sheet->getStyle("A{$rowIndex}:J{$rowIndex}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setARGB($fillColor);

                    // Color code the status column
                    $statusCell = "H{$rowIndex}";
                    $statusValue = $sheet->getCell($statusCell)->getValue();

                    $statusColor = match ($statusValue) {
                        'منقطع' => 'FFC7CE', // Red
                        'تم الاتصال' => 'FFE699', // Yellow
                        'تم التواصل' => 'C6EFCE', // Green
                        default => 'F2F2F2', // Gray
                    };

                    $textColor = match ($statusValue) {
                        'منقطع' => '9C0006',
                        'تم الاتصال' => '9C5700',
                        'تم التواصل' => '006100',
                        default => '7F7F7F',
                    };

                    $sheet->getStyle($statusCell)->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setARGB($statusColor);
                    $sheet->getStyle($statusCell)->getFont()->getColor()->setARGB($textColor);

                    // Color code the message response column
                    $responseCell = "C{$rowIndex}";
                    $responseValue = $sheet->getCell($responseCell)->getValue();

                    $responseColor = match ($responseValue) {
                        'نعم' => 'C6EFCE', // Green
                        'لا' => 'FFC7CE', // Red
                        default => 'F2F2F2', // Gray
                    };

                    $responseTextColor = match ($responseValue) {
                        'نعم' => '006100',
                        'لا' => '9C0006',
                        default => '7F7F7F',
                    };

                    $sheet->getStyle($responseCell)->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setARGB($responseColor);
                    $sheet->getStyle($responseCell)->getFont()->getColor()->setARGB($responseTextColor);
                }

                // Add borders to all cells
                $highestRow = $sheet->getHighestRow();
                $sheet->getStyle("A1:J{$highestRow}")->getBorders()->getAllBorders()
                    ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

                // Auto-adjust column widths
                foreach (range('A', 'J') as $column) {
                    $sheet->getColumnDimension($column)->setAutoSize(true);
                }
            },
        ];
    }
}
