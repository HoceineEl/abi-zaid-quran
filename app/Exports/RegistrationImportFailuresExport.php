<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class RegistrationImportFailuresExport implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings, WithTitle
{
    /**
     * @param  array<int, array{file: string, row: int|null, name: string, reason: string}>  $failures
     */
    public function __construct(protected array $failures) {}

    public function collection(): Collection
    {
        return collect($this->failures)->map(fn (array $failure): array => [
            $failure['file'],
            $failure['row'] ?? '—',
            $failure['name'] ?: '—',
            $failure['reason'],
        ]);
    }

    public function headings(): array
    {
        return ['الملف', 'رقم السطر', 'اسم الطالب', 'السبب'];
    }

    public function title(): string
    {
        return 'الأسطر غير المستوردة';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->setRightToLeft(true);
                $sheet->getStyle('A1:D1')->getFont()->setBold(true)->getColor()->setARGB('FFFFFF');
                $sheet->getStyle('A1:D1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('C00000');
                $sheet->freezePane('A2');
            },
        ];
    }
}
