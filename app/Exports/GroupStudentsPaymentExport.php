<?php

namespace App\Exports;

use App\Models\MemoGroup;
use Exception;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class GroupStudentsPaymentExport implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings, WithTitle
{
    protected $group;

    protected $selectedMonth;

    protected $selectedMonthName;

    public function __construct(MemoGroup $group, string $selectedMonth)
    {
        $this->group = $group;
        $this->selectedMonth = $selectedMonth;
        $this->selectedMonthName = $this->getArabicMonthName($selectedMonth);
    }

    private function getArabicMonthName(string $monthNumber): string
    {
        $months = [
            '01' => 'يناير',
            '02' => 'فبراير',
            '03' => 'مارس',
            '04' => 'أبريل',
            '05' => 'مايو',
            '06' => 'يونيو',
            '07' => 'يوليو',
            '08' => 'أغسطس',
            '09' => 'سبتمبر',
            '10' => 'أكتوبر',
            '11' => 'نوفمبر',
            '12' => 'ديسمبر',
        ];

        return $months[$monthNumber] ?? 'غير محدد';
    }

    public function collection()
    {
        return $this->group->memorizers()->with(['payments', 'attendances', 'guardian'])
            ->orderBy('name')
            ->get()
            ->map(function ($memorizer, $index) {
                // Get current year for payment checks
                $currentYear = now()->year;

                // Check payment status for selected month
                $selectedMonthPayment = $memorizer->payments()
                    ->whereYear('payment_date', $currentYear)
                    ->whereMonth('payment_date', intval($this->selectedMonth))
                    ->exists();

                // Determine payment status with exemption check
                $selectedPaymentStatus = $memorizer->exempt ? 'معفي' : ($selectedMonthPayment ? 'مدفوع' : 'غير مدفوع');

                // Get phone number (student's or guardian's)
                $phoneNumber = $memorizer->phone ?: ($memorizer->guardian?->phone ?? 'غير محدد');

                // Format phone number using Laravel's phone helper if available
                try {
                    $formattedPhone = $phoneNumber !== 'غير محدد' ? phone($phoneNumber, 'MA')->formatNational() : 'غير محدد';
                } catch (Exception $e) {
                    $formattedPhone = $phoneNumber; // fallback to original number if formatting fails
                }

                return [
                    'name' => $memorizer->name,
                    'academic_level' => '', // فارغ - empty as specified
                    'memorized_quran' => '', // فارغ - empty as specified
                    'whatsapp_number' => $formattedPhone,
                    'registration_form' => '', // فارغ - empty as specified
                    'payment_registration' => $selectedPaymentStatus, // واجب التسجيل - payment status
                    'payment_selected_month' => $selectedPaymentStatus, // واجب الشهر المحدد - payment status
                    'transfer_request' => '', // فارغ - empty as specified
                ];
            });
    }

    public function headings(): array
    {
        return [
            'اسم الطالب',
            'المستوى الدراسي',
            'المحفوظ من القرآن',
            'رقم الواتساب',
            'تعبئة ملف التسجيل',
            'واجب التسجيل',
            "واجب شهر {$this->selectedMonthName}",
            'طلب انتقال الى يوم آخر',
        ];
    }

    public function title(): string
    {
        return 'قائمة طلاب المجموعة - '.$this->group->name;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Set right-to-left direction for Arabic text
                $sheet->setRightToLeft(true);

                // Add title and group info as headings - insert 4 rows total (3 header rows + 1 payment parent header row)
                $sheet->insertNewRowBefore(1, 4);

                // Main title
                $sheet->mergeCells('A1:H1');
                $sheet->setCellValue('A1', 'تقرير طلاب المجموعة مع معلومات الدفع');

                // Group name and teacher
                $sheet->mergeCells('A2:H2');
                $groupInfo = 'المجموعة: '.$this->group->name;
                if ($this->group->teacher) {
                    $groupInfo .= ' - المدرس: '.$this->group->teacher->name;
                }
                $sheet->setCellValue('A2', $groupInfo);

                // Date and months info
                $sheet->mergeCells('A3:H3');
                $dateInfo = 'تاريخ التقرير: '.now()->format('Y-m-d').
                    ' - الشهر المحدد: '.$this->selectedMonthName;
                $sheet->setCellValue('A3', $dateInfo);

                // Parent header for 'أداء الواجب' - merge cells F4:G4
                $sheet->mergeCells('F4:G4');
                $sheet->setCellValue('F4', 'أداء الواجب');

                // Style the payment parent header
                $sheet->getStyle('F4')->getFont()->setBold(true)->setSize(12);
                $sheet->getStyle('F4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('F4')->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('B4C6E7'); // Light blue background for parent header

                // Style the main title
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
                $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('A1')->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('4472C4');
                $sheet->getStyle('A1')->getFont()->getColor()->setARGB('FFFFFF');

                // Style the group info
                $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);
                $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('A2')->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('D9E1F2');

                // Style the date info
                $sheet->getStyle('A3')->getFont()->setSize(10);
                $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('A3')->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('F2F2F2');

                // Style the headings row (which is now row 5)
                $sheet->getStyle('A5:H5')->getFont()->setBold(true);
                $sheet->getStyle('A5:H5')->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('E7E6E6');

                // Style the data rows
                if ($sheet->getHighestRow() < 6) {
                    return;
                }

                foreach ($sheet->getRowIterator(6) as $row) {
                    $rowIndex = $row->getRowIndex();

                    // Alternate row colors for better readability
                    $fillColor = $rowIndex % 2 == 0 ? 'F9F9F9' : 'FFFFFF';
                    $sheet->getStyle("A{$rowIndex}:H{$rowIndex}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setARGB($fillColor);

                    // Color code the registration payment column (column F)
                    $registrationPaymentCell = "F{$rowIndex}";
                    $registrationPaymentValue = $sheet->getCell($registrationPaymentCell)->getValue();
                    $this->applyPaymentCellColor($sheet, $registrationPaymentCell, $registrationPaymentValue);

                    // Color code the selected month payment column (column G)
                    $selectedMonthPaymentCell = "G{$rowIndex}";
                    $selectedMonthPaymentValue = $sheet->getCell($selectedMonthPaymentCell)->getValue();
                    $this->applyPaymentCellColor($sheet, $selectedMonthPaymentCell, $selectedMonthPaymentValue);
                }

                // Add borders to all cells
                $highestRow = $sheet->getHighestRow();
                $sheet->getStyle("A1:H{$highestRow}")->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);

                // Add special border for homework parent header
                $sheet->getStyle('F4:G4')->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_MEDIUM);

                // Auto-adjust column widths
                foreach (range('A', 'H') as $column) {
                    $sheet->getColumnDimension($column)->setAutoSize(true);
                }

                // Set minimum width for WhatsApp number column
                $sheet->getColumnDimension('D')->setWidth(15);
            },
        ];
    }

    private function applyPaymentCellColor($sheet, $cellAddress, $paymentValue): void
    {
        $fillColor = match ($paymentValue) {
            'مدفوع' => 'C6EFCE', // Green
            'غير مدفوع' => 'FFC7CE', // Red
            'معفي' => 'DDEBF7', // Light Blue
            default => 'F2F2F2', // Gray
        };

        $textColor = match ($paymentValue) {
            'مدفوع' => '006100', // Dark Green
            'غير مدفوع' => '9C0006', // Dark Red
            'معفي' => '0066CC', // Blue
            default => '7F7F7F', // Gray
        };

        $sheet->getStyle($cellAddress)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB($fillColor);
        $sheet->getStyle($cellAddress)->getFont()->getColor()->setARGB($textColor);
    }
}
