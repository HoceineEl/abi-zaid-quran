<?php

namespace App\Filament\Exports;

use App\Models\Student;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Illuminate\Database\Eloquent\Builder;
use Filament\Actions\Exports\Models\Export;

class ProgressExporter extends Exporter
{
    public static function getColumns(): array
    {
        $columns = [
            ExportColumn::make('name')
                ->label('الطالب'),
            ExportColumn::make('phone')
                ->label('رقم الهاتف'),
            ExportColumn::make('city')
                ->label('المدينة'),
            ExportColumn::make('group.name')
                ->label('المجموعة'),
            ExportColumn::make('sex')
                ->label('الجنس')
                ->formatStateUsing(fn($state) => match ($state) {
                    'male' => 'ذكر',
                    'female' => 'أنثى',
                })
                ->default('ذكر'),
        ];

        $startDate = now()->subDays(29);
        $endDate = now();

        for ($date = $startDate; $date <= $endDate; $date->addDay()) {
            $formattedDate = $date->format('Y-m-d');
            $columns[] = ExportColumn::make("status_day_{$formattedDate}")
                ->label($date->format('d/m'))
                ->state(function (Student $record) use ($formattedDate) {
                    $progress = $record->progresses->where('date', $formattedDate)->first();
                    return $progress ? ($progress->status === 'memorized' ? 'حاضر' : 'غائب') : 'غير مسجل';
                });
        }
        return $columns;
    }

    public static function modifyQuery(Builder $query): Builder
    {
        return $query->with(['progresses' => function ($query) {
            $query->whereBetween('date', [now()->subDays(29), now()]);
        }]);
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'تم تصدير ' . number_format($export->successful_rows) . ' ' . str('صف')->plural($export->successful_rows) . ' بنجاح.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' فشل تصدير ' . number_format($failedRowsCount) . ' ' . str('صف')->plural($failedRowsCount) . '.';
        }

        return $body;
    }

    public function getJobConnection(): ?string
    {
        return 'sync';
    }
}
