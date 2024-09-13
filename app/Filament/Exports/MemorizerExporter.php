<?php

namespace App\Filament\Exports;

use App\Models\Memorizer;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class MemorizerExporter extends Exporter
{
    protected static ?string $model = Memorizer::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('name')
                ->label('الإسم'),
            ExportColumn::make('phone')
                ->label('الهاتف'),
            ExportColumn::make('sex')
                ->label('الجنس')
                ->state(fn(Memorizer $record) => $record->sex === 'male' ? 'ذكر' : 'أنثى'),
            ExportColumn::make('city')
                ->label('المدينة'),
            ExportColumn::make('group.name')
                ->label('المجموعة'),
            ExportColumn::make('exempt')
                ->label('معفى')
                ->state(fn(Memorizer $record) => $record->exempt ? 'نعم' : 'لا'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'تم تصدير ' . number_format($export->successful_rows) . ' ' . str('صف')->plural($export->successful_rows) . ' بنجاح.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' فشل تصدير ' . number_format($failedRowsCount) . ' ' . str('صف')->plural($failedRowsCount) . '.';
        }

        return $body;
    }
}
