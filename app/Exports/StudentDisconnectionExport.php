<?php

namespace App\Exports;

use App\Enums\DisconnectionStatus;
use App\Enums\MessageResponseStatus;
use App\Enums\StudentReactionStatus;
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
    protected $includeReturned;

    public function __construct(string $dateRange = 'جميع الطلاب المنقطعين', ?string $startDate = null, ?string $endDate = null, bool $includeReturned = true)
    {
        $this->dateRange = $dateRange;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->includeReturned = $includeReturned;
    }

    public function collection()
    {
        $query = StudentDisconnection::with(['student', 'group']);

        if ($this->startDate && $this->endDate) {
            // Filter by student's last attendance date (last present date)
            $query->whereHas('student', function ($studentQuery) {
                $studentQuery->whereHas('progresses', function ($progressQuery) {
                    $progressQuery->where('status', 'memorized')
                        ->whereBetween('date', [$this->startDate, $this->endDate])
                        ->whereRaw('date = (SELECT MAX(p2.date) FROM progress p2 WHERE p2.student_id = progress.student_id AND p2.status = "memorized")');
                });
            });
        }

        // Filter by returned status if specified
        if (!$this->includeReturned) {
            $query->where('has_returned', false);
        }

        return $query->orderBy('created_at', 'desc')->get()
            ->values()
            ->map(function ($disconnection, $index) {
                $daysSinceLastPresent = $disconnection->student->getDaysSinceLastPresentAttribute();
                $disconnectionDuration = $daysSinceLastPresent === null ? 'غير محدد' : $daysSinceLastPresent . ' يوم';

                $messageResponse = match ($disconnection->message_response) {
                    MessageResponseStatus::NotContacted => 'لم يتم التواصل',
                    MessageResponseStatus::ReminderMessage => 'الرسالة التذكيرية',
                    MessageResponseStatus::WarningMessage => 'الرسالة الإندارية',
                    null => 'لم يتم التواصل',
                    default => 'لم يتم التواصل',
                };

                $studentReaction = match ($disconnection->student_reaction) {
                    StudentReactionStatus::ReactedToReminder => 'تفاعل مع التذكير',
                    StudentReactionStatus::ReactedToWarning => 'تفاعل مع الإنذار',
                    StudentReactionStatus::PositiveResponse => 'استجابة إيجابية',
                    StudentReactionStatus::NegativeResponse => 'استجابة سلبية',
                    StudentReactionStatus::NoResponse => 'لم يستجب',
                    null => 'لا يوجد',
                    default => 'لا يوجد',
                };

                // Check if student has returned
                if ($disconnection->has_returned) {
                    $status = 'عاد';
                } else {
                    $status = match ($disconnection->status) {
                        DisconnectionStatus::Disconnected => 'منقطع',
                        DisconnectionStatus::Contacted => 'تم الاتصال',
                        DisconnectionStatus::Responded => 'تم التواصل',
                        null => 'غير محدد',
                        default => 'غير محدد',
                    };
                }

                return [
                    'student_order' => $index + 1,
                    'student_name' => $disconnection->student->name,
                    'notes' => $disconnection->notes ?? '',
                    'message_response' => $messageResponse,
                    'group_name' => $disconnection->group->name,
                    'disconnection_date' => Carbon::parse($disconnection->disconnection_date)->format('Y-m-d'),
                    'disconnection_duration' => $disconnectionDuration,
                    'contact_date' => $disconnection->contact_date ? Carbon::parse($disconnection->contact_date)->format('Y-m-d') : 'لم يتم التواصل',
                    'reminder_message_date' => $disconnection->reminder_message_date ? Carbon::parse($disconnection->reminder_message_date)->format('Y-m-d') : 'لم يتم الإرسال',
                    'warning_message_date' => $disconnection->warning_message_date ? Carbon::parse($disconnection->warning_message_date)->format('Y-m-d') : 'لم يتم الإرسال',
                    'student_reaction' => $studentReaction,
                    'student_reaction_date' => $disconnection->student_reaction_date ? Carbon::parse($disconnection->student_reaction_date)->format('Y-m-d') : 'لا يوجد',
                    'status' => $status,
                ];
            });
    }

    public function headings(): array
    {
        return [
            'الترتيب',
            'اسم الطالب',
            'ملاحظات',
            'حالة التواصل',
            'المجموعة',
            'تاريخ الانقطاع',
            'مدة الانقطاع',
            'تاريخ التواصل',
            'تاريخ الرسالة التذكيرية',
            'تاريخ الرسالة الإندارية',
            'تفاعل الطالب',
            'تاريخ التفاعل',
            'الحالة',
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
                $sheet->mergeCells('A1:M1');
                $sheet->setCellValue('A1', 'تقرير الطلاب المنقطعين');

                $sheet->mergeCells('A2:M2');
                $dateRangeText = 'النطاق الزمني: ' . $this->dateRange;
                if (!$this->includeReturned) {
                    $dateRangeText .= ' (لا يشمل الطلاب العائدين)';
                }
                $sheet->setCellValue('A2', $dateRangeText);

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
                $sheet->getStyle('A3:M3')->getFont()->setBold(true);
                $sheet->getStyle('A3:M3')->getFill()
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
                    $sheet->getStyle("A{$rowIndex}:M{$rowIndex}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setARGB($fillColor);

                    // Color code the status column (now M instead of K)
                    $statusCell = "M{$rowIndex}";
                    $statusValue = $sheet->getCell($statusCell)->getValue();

                    $statusColor = match ($statusValue) {
                        'منقطع' => 'FFC7CE', // Red
                        'تم الاتصال' => 'FFE699', // Yellow
                        'تم التواصل' => 'C6EFCE', // Green
                        'عاد' => '90EE90', // Light Green
                        default => 'F2F2F2', // Gray
                    };

                    $textColor = match ($statusValue) {
                        'منقطع' => '9C0006',
                        'تم الاتصال' => '9C5700',
                        'تم التواصل' => '006100',
                        'عاد' => '006400', // Dark Green
                        default => '7F7F7F',
                    };

                    $sheet->getStyle($statusCell)->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setARGB($statusColor);
                    $sheet->getStyle($statusCell)->getFont()->getColor()->setARGB($textColor);

                    // Color code the message response column
                    $responseCell = "D{$rowIndex}";
                    $responseValue = $sheet->getCell($responseCell)->getValue();

                    $responseColor = match ($responseValue) {
                        'الرسالة التذكيرية' => 'B4C7E7', // Light Blue
                        'الرسالة الإندارية' => 'FFD966', // Light Orange
                        default => 'F2F2F2', // Gray
                    };

                    $responseTextColor = match ($responseValue) {
                        'الرسالة التذكيرية' => '1F4E78',
                        'الرسالة الإندارية' => '9C5700',
                        default => '7F7F7F',
                    };

                    $sheet->getStyle($responseCell)->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setARGB($responseColor);
                    $sheet->getStyle($responseCell)->getFont()->getColor()->setARGB($responseTextColor);

                    // Color code the student reaction column (K)
                    $reactionCell = "K{$rowIndex}";
                    $reactionValue = $sheet->getCell($reactionCell)->getValue();

                    $reactionColor = match ($reactionValue) {
                        'تفاعل مع التذكير' => 'B4C7E7', // Light Blue
                        'تفاعل مع الإنذار' => 'FFD966', // Light Orange
                        'استجابة إيجابية' => 'C6EFCE', // Green
                        'استجابة سلبية' => 'FFC7CE', // Red
                        'لم يستجب' => 'F2F2F2', // Gray
                        default => 'FFFFFF', // White
                    };

                    $reactionTextColor = match ($reactionValue) {
                        'تفاعل مع التذكير' => '1F4E78',
                        'تفاعل مع الإنذار' => '9C5700',
                        'استجابة إيجابية' => '006100',
                        'استجابة سلبية' => '9C0006',
                        'لم يستجب' => '7F7F7F',
                        default => '000000',
                    };

                    $sheet->getStyle($reactionCell)->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setARGB($reactionColor);
                    $sheet->getStyle($reactionCell)->getFont()->getColor()->setARGB($reactionTextColor);
                }

                // Add borders to all cells
                $highestRow = $sheet->getHighestRow();
                $sheet->getStyle("A1:M{$highestRow}")->getBorders()->getAllBorders()
                    ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

                // Auto-adjust column widths
                foreach (range('A', 'M') as $column) {
                    if ($column === 'C') { // Notes column
                        $sheet->getColumnDimension($column)->setWidth(30);
                    } else {
                        $sheet->getColumnDimension($column)->setAutoSize(true);
                    }
                }
            },
        ];
    }
}
