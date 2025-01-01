<?php

namespace App\Filament\Imports;

use App\Models\Memorizer;
use App\Models\MemoGroup;
use App\Models\Teacher;
use App\Models\Guardian;
use App\Models\Round;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Illuminate\Support\Carbon;

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

            ImportColumn::make('guardian_name')
                ->label('اسم ولي الأمر')
                ->rules(['required', 'string'])
                ->guess(['الأب أو الولي', 'اسم ولي الأمر', 'ولي الأمر', 'الولي', 'ولي الأمر', 'ولي الأمر'])
                ->fillRecordUsing(function (Memorizer $record, string $state, array $options) {
                    if (isset($options['guardian_id'])) {
                        $record->guardian_id = $options['guardian_id'];
                    } else {
                        $guardian = Guardian::firstOrCreate(
                            ['phone' => $record->phone ?? null],
                            [
                                'name' => $state,
                                'city' => $record->city ?? 'أسفي',
                            ]
                        );
                        $record->guardian_id = $guardian->id;
                    }
                }),

            ImportColumn::make('guardian_phone')
                ->label('هاتف ولي الأمر')
                ->rules(['nullable', 'string'])
                ->guess(['هاتف ولي الأمر', 'رقم ولي الأمر', 'رقم الهاتف', 'الهاتف'])
                ->fillRecordUsing(function (Memorizer $record, ?string $state) {
                    if ($record->guardian_id && $state) {
                        Guardian::where('id', $record->guardian_id)->update(['phone' => $state]);
                    }
                }),

            ImportColumn::make('group')
                ->label('الفئة')
                ->rules(['required', 'string'])
                ->guess(['الفئة', 'المجموعة', 'الصف'])
                ->fillRecordUsing(function (Memorizer $record, string $state, array $options) {
                    if (isset($options['group_id'])) {
                        $record->memo_group_id = $options['group_id'];
                    } else {
                        $group = MemoGroup::firstOrCreate(['name' => $state], ['price' => 100]);
                        $record->memo_group_id = $group->id;
                    }
                }),

            ImportColumn::make('teacher')
                ->label('الأستاذ')
                ->rules(['required', 'string'])
                ->guess(['الأستاذ', 'المعلم', 'المدرس', 'أستاذ'])
                ->fillRecordUsing(function (Memorizer $record, string $state, array $options) {
                    if (isset($options['teacher_id'])) {
                        $record->teacher_id = $options['teacher_id'];
                    } else {
                        $teacher = Teacher::firstOrCreate(['name' => $state]);
                        $record->teacher_id = $teacher->id;
                    }
                }),

            ImportColumn::make('round')
                ->label('الحلقة')
                ->rules(['nullable', 'string'])
                ->guess(['الحلقة', 'الفترة', 'الوقت'])
                ->fillRecordUsing(function (Memorizer $record, ?string $state, array $options) {
                    if (isset($options['round_id'])) {
                        $record->round_id = $options['round_id'];
                    } elseif ($state) {
                        // Default to all weekdays if not specified
                        $defaultDays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
                        $round = Round::firstOrCreate(
                            ['name' => $state],
                            ['days' => $defaultDays]
                        );
                        $record->round_id = $round->id;
                    }
                }),

            ImportColumn::make('phone')
                ->label('رقم الهاتف الخاص')
                ->rules(['nullable', 'string'])
                ->guess(['رقم الهاتف الخاص', 'الهاتف الخاص', 'رقم الهاتف', 'الهاتف'])
                ->fillRecordUsing(function (Memorizer $record, ?string $state) {
                    $record->phone = $state;
                }),

            ImportColumn::make('address')
                ->label('العنوان')
                ->rules(['nullable', 'string'])
                ->guess(['العنوان', 'المقر', 'السكن']),
            ImportColumn::make('birth_date')
                ->label('تاريخ الإزدياد')
                ->rules(['nullable', 'date'])
                ->guess(['تاريخ الإزدياد', 'الإزدياد', 'تاريخ الميلاد'])
                ->castStateUsing(function (?string $state) {
                    return $state ? Carbon::parse($state)->format('Y-m-d') : null;
                })
                ->example('2000-01-01'),

            ImportColumn::make('sex')
                ->label('الجنس')
                ->rules(['nullable', 'string'])
                ->guess(['الجنس'])
                ->example('ذكر')
                ->fillRecordUsing(function (Memorizer $record, ?string $state) {
                    $record->sex = $this->mapSex($state ?? 'ذكر');
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
            'أنثى', 'مرأة'  => 'female',
            default => 'male',
        };
    } 

    public static function getOptionsFormComponents(): array
    {
        return [
            Select::make('group_id')
                ->label('المجموعة')
                ->options(fn() => MemoGroup::all()->pluck('name', 'id'))
                ->placeholder('اختر المجموعة (اختياري)')
                ->helperText('إذا تم تحديد مجموعة، سيتم تجاهل عمود المجموعة في ملف الاستيراد'),

            Select::make('teacher_id')
                ->label('الأستاذ(ة)')
                ->options(fn() => Teacher::all()->pluck('name', 'id'))
                ->placeholder('اختر الأستاذ(ة) (اختياري)')
                ->helperText('إذا تم تحديد أستاذ(ة)، سيتم تجاهل عمود الأستاذ(ة) في ملف الاستيراد'),

            Select::make('round_id')
                ->label('الحلقة')
                ->options(fn() => Round::all()->pluck('name', 'id'))
                ->placeholder('اختر الحلقة (اختياري)')
                ->helperText('إذا تم تحديد الحلقة، سيتم تجاهل عمود الحلقة في ملف الاستيراد'),

            Select::make('guardian_id')
                ->label('ولي الأمر')
                ->options(fn() => Guardian::all()->pluck('name', 'id'))
                ->placeholder('اختر ولي الأمر (اختياري)')
                ->helperText('إذا تم تحديد ولي الأمر، سيتم تجاهل عمود ولي الأمر في ملف الاستيراد'),

            Checkbox::make('updateExisting')
                ->label('تحديث السجلات الموجودة'),
        ];
    }
    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'تم استيراد ' . number_format($import->successful_rows) . ' ' . str('سجل')->plural($import->successful_rows) . ' بنجاح.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' فشل استيراد ' . number_format($failedRowsCount) . ' ' . str('سجل')->plural($failedRowsCount) . '.';
        }

        return $body;
    }
}
