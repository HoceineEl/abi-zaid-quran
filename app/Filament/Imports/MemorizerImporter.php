<?php

namespace App\Filament\Imports;

use App\Models\Memorizer;
use App\Models\MemoGroup;
use App\Models\Teacher;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Filament\Forms\Components\Checkbox;

class MemorizerImporter extends Importer
{
    protected static ?string $model = Memorizer::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('name')
                ->label('الاسم الكامل')
                ->requiredMapping()
                ->rules(['required', 'string'])
                ->guess(['الاسم الكامل', 'الاسم', 'اسم الطالب']),

            ImportColumn::make('group')
                ->label('الفئة')
                ->rules(['required', 'string'])
                ->guess(['الفئة', 'المجموعة', 'الصف'])
                ->fillRecordUsing(function (Memorizer $record, string $state) {
                    $group = MemoGroup::firstOrCreate(['name' => $state], ['price' => 100]);
                    $record->memo_group_id = $group->id;
                }),

            ImportColumn::make('teacher')
                ->label('الأستاذة')
                ->requiredMapping()
                ->rules(['required', 'string'])
                ->guess(['الأستاذة', 'المعلمة', 'المدرسة'])
                ->fillRecordUsing(function (Memorizer $record, string $state) {
                    $teacher = Teacher::firstOrCreate(['name' => $state]);
                    $record->teacher_id = $teacher->id;
                }),

            ImportColumn::make('payment_status')
                ->label('واجب التسجيل')
                ->rules(['nullable', 'string'])
                ->guess(['واجب التسجيل', 'حالة الدفع', 'الرسوم'])
                ->fillRecordUsing(function (Memorizer $record, ?string $state) {
                    $record->exempt = $state === 'معفى';
                }),

            ImportColumn::make('phone')
                ->label('رقم الهاتف')
                ->rules(['nullable', 'string'])
                ->guess(['رقم الهاتف', 'الهاتف', 'رقم الجوال'])
                ->fillRecordUsing(function (Memorizer $record, ?string $state) {
                    $record->phone = $state ?? '';
                }),

            ImportColumn::make('sex')
                ->label('الجنس')
                ->rules(['nullable', 'string'])
                ->guess(['الجنس', 'النوع'])
                ->example('أنثى')
                ->fillRecordUsing(function (Memorizer $record, ?string $state) {
                    $record->sex = $this->mapSex($state ?? 'أنثى');
                }),

            ImportColumn::make('city')
                ->label('المدينة')
                ->rules(['nullable', 'string'])
                ->guess(['المدينة', 'البلدة', 'المكان'])
                ->example('أسفي'),
        ];
    }

    public function resolveRecord(): ?Memorizer
    {
        if ($this->options['updateExisting'] ?? false) {
            return Memorizer::firstOrNew(['name' => $this->data['name']]);
        }

        return new Memorizer();
    }

    protected function mapSex(?string $sex): string
    {
        return match (trim(strtolower($sex))) {
            'ذكر', 'رجل', 'صبي', 'ولد' => 'male',
            default => 'female',
        };
    }

    public static function getOptionsFormComponents(): array
    {
        return [
            Checkbox::make('updateExisting')
                ->label('تحديث السجلات الموجودة')
        ];
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'تم استيراد ' . number_format($import->successful_rows) . ' ' . str('صف')->plural($import->successful_rows) . ' بنجاح.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' فشل استيراد ' . number_format($failedRowsCount) . ' ' . str('صف')->plural($failedRowsCount) . '.';
        }

        return $body;
    }
}
