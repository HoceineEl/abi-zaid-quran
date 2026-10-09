<?php

namespace App\Exports;

use App\Models\Memorizer;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
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

class MemorizersFilteredExport implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings, WithTitle
{
    protected int $rowsCount = 0;

    public function __construct(
        protected Builder $query,
        protected string $heading = 'لائحة الطلاب',
    ) {}

    public function collection(): Collection
    {
        $rows = $this->query
            ->with(['group.teacher', 'guardian', 'round'])
            ->get()
            ->map(function (Memorizer $memorizer, int $index) {
                $birthDate = $memorizer->birth_date ? Carbon::parse($memorizer->birth_date) : null;

                return [
                    'order' => $index + 1,
                    'number' => $memorizer->number,
                    'name' => $memorizer->name,
                    'sex' => $memorizer->sex === 'female' ? 'أنثى' : 'ذكر',
                    'birth_date' => $birthDate?->format('Y-m-d') ?? '',
                    'age' => $birthDate?->age ?? '',
                    'school_level' => $memorizer->school_level?->getLabel() ?? '',
                    'memorized_hizbs' => $memorizer->memorized_hizbs ?? '',
                    'attended_summer_session' => $memorizer->attended_summer_session ? 'نعم' : 'لا',
                    'phone' => $memorizer->phone ?: '',
                    'guardian_name' => $memorizer->guardian?->name ?? '',
                    'guardian_phone' => $memorizer->guardian?->phone ?? '',
                    'group' => $memorizer->group?->name ?? '',
                    'teacher' => $memorizer->group?->teacher?->name ?? '',
                    'round' => $memorizer->round?->name ?? '',
                    'city' => $memorizer->city ?? '',
                    'address' => $memorizer->address ?? '',
                    'exempt' => $memorizer->exempt ? 'نعم' : 'لا',
                    'paid_this_month' => $memorizer->has_payment_this_month ? 'نعم' : 'لا',
                    'created_at' => $memorizer->created_at?->format('Y-m-d') ?? '',
                ];
            });

        $this->rowsCount = $rows->count();

        return $rows;
    }

    public function headings(): array
    {
        return [
            '#',
            'الرقم',
            'الإسم',
            'الجنس',
            'تاريخ الإزدياد',
            'العمر',
            'المستوى الدراسي',
            'المحفوظ الإجمالي (حزب)',
            'حضر الدورة الصيفية',
            'الهاتف',
            'اسم ولي الأمر',
            'هاتف ولي الأمر',
            'المجموعة',
            'الأستاذ',
            'الدورة',
            'المدينة',
            'العنوان',
            'معفى',
            'أدى هذا الشهر',
            'تاريخ التسجيل',
        ];
    }

    public function title(): string
    {
        return 'الطلاب';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->setRightToLeft(true);

                $lastCol = 'T';
                $sheet->insertNewRowBefore(1, 2);

                $sheet->mergeCells("A1:{$lastCol}1");
                $sheet->setCellValue('A1', $this->heading);
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16)->getColor()->setARGB('FFFFFF');
                $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle('A1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('2F5496');
                $sheet->getRowDimension(1)->setRowHeight(35);

                $sheet->mergeCells("A2:{$lastCol}2");
                $sheet->setCellValue('A2', 'عدد الطلاب: '.$this->rowsCount.'  |  تاريخ التصدير: '.now()->format('Y-m-d H:i'));
                $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $headerRow = 3;
                $lastRow = $headerRow + $this->rowsCount;

                $sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->getFont()->setBold(true)->getColor()->setARGB('FFFFFF');
                $sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('4472C4');
                $sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getRowDimension($headerRow)->setRowHeight(24);

                if ($this->rowsCount > 0) {
                    $sheet->getStyle("A{$headerRow}:{$lastCol}{$lastRow}")
                        ->getBorders()
                        ->getAllBorders()
                        ->setBorderStyle(Border::BORDER_THIN);

                    $sheet->getStyle('A'.($headerRow + 1).":{$lastCol}{$lastRow}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                        ->setVertical(Alignment::VERTICAL_CENTER);

                    $sheet->setAutoFilter("A{$headerRow}:{$lastCol}{$lastRow}");
                }

                $sheet->freezePane('A'.($headerRow + 1));
            },
        ];
    }
}
