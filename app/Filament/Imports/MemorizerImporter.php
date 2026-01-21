<?php

namespace App\Filament\Imports;

use App\Models\Memorizer;
use App\Models\MemoGroup;
use App\Models\Teacher;
use App\Models\Guardian;
use App\Models\Round;
use App\Models\User;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

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
                ->rules(['nullable', 'string'])
                ->guess(['الفئة', 'المجموعة', 'الصف'])
                ->fillRecordUsing(function (Memorizer $record, ?string $state, array $options) {
                    // If group_id option is set, ALWAYS use it (ignore file's group column)
                    if (isset($options['group_id'])) {
                        $record->memo_group_id = $options['group_id'];
                        return;
                    }

                    if (!$state) {
                        return;
                    }

                    $teacherId = $options['teacher_id'] ?? null;
                    $defaultSex = $options['default_sex'] ?? 'male';
                    $groupName = trim($state);

                    // Try to find or create teacher
                    if (!$teacherId) {
                        $teacherName = null;

                        // Extract teacher name from group name if it contains "-"
                        if (str_contains($groupName, '-')) {
                            $parts = explode('-', $groupName, 2);
                            $teacherName = trim($parts[0]);
                        } else {
                            // Use the group name itself as teacher name
                            $teacherName = $groupName;
                        }

                        if ($teacherName) {
                            // Normalize Arabic characters for matching
                            $normalizedName = str_replace(['أ', 'إ', 'آ', 'ى'], ['ا', 'ا', 'ا', 'ي'], $teacherName);

                            // Try to find existing teacher with similar name (any sex first)
                            $teacher = User::where('role', 'teacher')
                                ->where(function ($query) use ($teacherName, $normalizedName) {
                                    $query->where('name', $teacherName)
                                        ->orWhere('name', 'like', $teacherName . '%')
                                        ->orWhere('name', 'like', $normalizedName . '%')
                                        ->orWhere('name', 'like', '%' . $teacherName . '%');
                                })
                                ->first();

                            // If no teacher found, create one with the default sex
                            if (!$teacher) {
                                $teacher = User::create([
                                    'name' => $teacherName,
                                    'role' => 'teacher',
                                    'phone' => '0666666666',
                                    'sex' => $defaultSex,
                                    'password' => bcrypt('teacher'),
                                    'email' => Str::slug($teacherName) . rand(100, 999) . '@abi-zaid.com',
                                ]);
                            }

                            $teacherId = $teacher->id;
                        }
                    }

                    $group = MemoGroup::firstOrCreate(
                        ['name' => $groupName],
                        ['price' => 70, 'teacher_id' => $teacherId]
                    );

                    // Update teacher if group exists but has no teacher
                    if (!$group->teacher_id && $teacherId) {
                        $group->update(['teacher_id' => $teacherId]);
                    }

                    $record->memo_group_id = $group->id;
                }),

            ImportColumn::make('teacher')
                ->label('الأستاذ')
                ->rules(['nullable', 'string'])
                ->guess(['الأستاذ', 'المعلم', 'المدرس', 'أستاذ'])
                ->fillRecordUsing(function (Memorizer $record, ?string $state, array $options) {
                    // Skip if no group or no teacher state/option
                    if (!$record->memo_group_id || (!$state && !isset($options['teacher_id']))) {
                        return;
                    }

                    $group = MemoGroup::find($record->memo_group_id);
                    if (!$group || $group->teacher_id) {
                        // Skip if group already has a teacher (assigned by group column)
                        return;
                    }

                    if (isset($options['teacher_id'])) {
                        $group->update(['teacher_id' => $options['teacher_id']]);
                    } elseif ($state) {
                        $defaultSex = $options['default_sex'] ?? 'male';
                        $teacher = User::firstOrCreate(['name' => $state, 'role' => 'teacher'], [
                            'phone' => '0666666666',
                            'sex' => $defaultSex,
                            'password' => bcrypt('teacher'),
                            'email' => Str::slug($state) . rand(100, 999) . '@abi-zaid.com',
                        ]);
                        $group->update(['teacher_id' => $teacher->id]);
                    }
                }),


            ImportColumn::make('phone')
                ->label('رقم الهاتف الخاص')
                ->rules(['nullable', 'string'])
                ->guess(['رقم الهاتف الخاص', 'الهاتف الخاص', 'رقم الهاتف', 'الهاتف', 'الهاتف (الخاص)'])
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

            ImportColumn::make('exempt')
                ->label('معفى')
                ->rules(['nullable', 'boolean'])
                ->guess(['معفى', 'معفي', 'الإعفاء', 'Mo3fa', 'ma3fi'])
                ->castStateUsing(function (?string $state) {
                    if ($state === null || $state === '') {
                        return false;
                    }
                    return in_array(mb_strtolower(trim($state)), ['نعم', 'معفى', 'معفي', '1', 'true', 'yes'], true);
                }),

        ];
    }

    public function resolveRecord(): ?Memorizer
    {
        $name = $this->data['name'] ?? null;

        if (!$name) {
            return null;
        }

        if ($this->options['updateExisting'] ?? false) {
            return Memorizer::firstOrNew(['name' => $name]);
        }

        // Skip if memorizer with same name already exists
        if (Memorizer::where('name', $name)->exists()) {
            return null;
        }

        return new Memorizer();
    }

    protected function beforeSave(): void
    {
        // Ensure group is assigned from option if not already set
        if (empty($this->record->memo_group_id) && isset($this->options['group_id'])) {
            $this->record->memo_group_id = $this->options['group_id'];
        }

        // Skip record if no group assigned
        if (empty($this->record->memo_group_id)) {
            throw new \Filament\Actions\Imports\Exceptions\RowImportFailedException('لم يتم تحديد المجموعة');
        }

        // Ensure the group has a teacher assigned (for sex derivation)
        if ($this->record->memo_group_id && isset($this->options['teacher_id'])) {
            $group = MemoGroup::find($this->record->memo_group_id);
            if ($group && !$group->teacher_id) {
                $group->update(['teacher_id' => $this->options['teacher_id']]);
            }
        }
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
                ->searchable()
                ->preload()
                ->placeholder('اختر المجموعة (اختياري)')
                ->helperText('إذا تم تحديد مجموعة، سيتم تجاهل عمود المجموعة في ملف الاستيراد'),

            Select::make('teacher_id')
                ->label('الأستاذ(ة)')
                ->options(fn() => User::where('role', 'teacher')->pluck('name', 'id'))
                ->placeholder('اختر الأستاذ(ة) (اختياري)')
                ->helperText('إذا تم تحديد أستاذ(ة)، سيتم تجاهل عمود الأستاذ(ة) في ملف الاستيراد'),

            Select::make('default_sex')
                ->label('الجنس الافتراضي')
                ->options([
                    'male' => 'ذكر',
                    'female' => 'أنثى',
                ])
                ->default('male')
                ->helperText('الجنس الافتراضي للأساتذة الجدد الذين يتم إنشاؤهم أثناء الاستيراد'),

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

    public function getJobConnection(): ?string
    {
        return 'sync';
    }
}
