<?php

namespace App\Services;

use App\Enums\PaymentType;
use App\Enums\SchoolLevel;
use App\Models\Guardian;
use App\Models\Memorizer;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

class RegistrationFormsImportService
{
    public const TEMPLATE_PATH = 'templates/registration-template.xlsx';

    public const REGISTRATION_FEE = 130;

    public const MONTHLY_FEE = 70;

    protected const COLUMNS = [
        'name' => 'اسم الطالب',
        'birth_date' => 'تاريخ الازدياد',
        'school_level' => 'المستوى الدراسي',
        'memorized_hizbs' => 'المحفوظ الإجمالي',
        'guardian_name' => 'اسم ولي الأمر',
        'phone' => 'رقم الواتساب',
        'registration_paid' => 'أدى واجب التسجيل',
        'october_paid' => 'أدى واجب شهر أكتوبر',
        'exempt' => 'معفى',
        'notes' => 'ملاحظات',
    ];

    protected int $created = 0;

    protected int $duplicates = 0;

    protected int $registrationPayments = 0;

    protected int $monthlyPayments = 0;

    /** @var array<int, array{file: string, row: int|null, name: string, reason: string}> */
    protected array $failures = [];

    /** @var array<string, true> */
    protected array $seenInUpload = [];

    /**
     * @param  array<string, string>  $files  absolute path => original file name
     * @return array{created: int, duplicates: int, registration_payments: int, monthly_payments: int, failures: array<int, array{file: string, row: int|null, name: string, reason: string}>}
     */
    public function import(array $files): array
    {
        foreach ($files as $path => $fileName) {
            $this->importFile($path, $fileName);
        }

        return [
            'created' => $this->created,
            'duplicates' => $this->duplicates,
            'registration_payments' => $this->registrationPayments,
            'monthly_payments' => $this->monthlyPayments,
            'failures' => $this->failures,
        ];
    }

    protected function importFile(string $path, string $fileName): void
    {
        try {
            $sheets = Excel::toArray(new class {}, $path);
        } catch (Throwable) {
            $this->fail($fileName, null, '', 'تعذرت قراءة الملف. تأكد أنه ملف Excel سليم.');

            return;
        }

        foreach ($sheets as $rows) {
            $columns = $this->mapColumns($rows[0] ?? []);

            if (! isset($columns['name'], $columns['phone'])) {
                continue;
            }

            foreach (array_slice($rows, 1, preserve_keys: true) as $index => $row) {
                $this->importRow($this->extract($row, $columns), $fileName, $index + 1);
            }

            return;
        }

        $this->fail($fileName, null, '', 'لم يتم العثور على ورقة «التلاميذ». استعمل نموذج التسجيل دون تغيير العناوين.');
    }

    /**
     * @param  array<int, mixed>  $headerRow
     * @return array<string, int>
     */
    protected function mapColumns(array $headerRow): array
    {
        $columns = [];

        foreach ($headerRow as $index => $heading) {
            $heading = trim((string) $heading);

            foreach (self::COLUMNS as $key => $label) {
                if (! isset($columns[$key]) && str_starts_with($heading, $label)) {
                    $columns[$key] = $index;
                }
            }
        }

        return $columns;
    }

    /**
     * @param  array<int, mixed>  $row
     * @param  array<string, int>  $columns
     * @return array<string, mixed>
     */
    protected function extract(array $row, array $columns): array
    {
        return collect(self::COLUMNS)
            ->mapWithKeys(fn (string $label, string $key): array => [
                $key => isset($columns[$key]) ? $this->clean($row[$columns[$key]] ?? null) : null,
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function importRow(array $data, string $fileName, int $rowNumber): void
    {
        $name = preg_replace('/\s+/u', ' ', (string) $data['name']);

        if (blank($name) && blank($data['phone'])) {
            return;
        }

        if (blank($name)) {
            $this->fail($fileName, $rowNumber, '', 'اسم الطالب فارغ.');

            return;
        }

        $phone = $this->formatPhone($data['phone']);

        if (! $phone) {
            $this->fail($fileName, $rowNumber, $name, 'رقم الواتساب فارغ أو غير صحيح: '.($data['phone'] ?? '—'));

            return;
        }

        $birthDate = $this->parseDate($data['birth_date']);

        if (filled($data['birth_date']) && ! $birthDate) {
            $this->fail($fileName, $rowNumber, $name, 'تاريخ الازدياد غير صحيح: '.$data['birth_date'].'. اكتبه هكذا 15/03/2016.');

            return;
        }

        $duplicateKey = mb_strtolower($name).'|'.$phone;

        if (isset($this->seenInUpload[$duplicateKey]) || Memorizer::where('name', $name)->where('phone', $phone)->exists()) {
            $this->duplicates++;

            return;
        }

        $this->seenInUpload[$duplicateKey] = true;

        $exempt = $this->isYes($data['exempt']);
        $hizbs = is_numeric($data['memorized_hizbs']) ? (int) $data['memorized_hizbs'] : null;

        DB::transaction(function () use ($data, $name, $phone, $birthDate, $exempt, $hizbs): void {
            $guardian = filled($data['guardian_name'])
                ? Guardian::firstOrCreate(['phone' => $phone], ['name' => $data['guardian_name']])
                : null;

            $memorizer = Memorizer::create([
                'name' => $name,
                'phone' => $phone,
                'birth_date' => $birthDate,
                'school_level' => filled($data['school_level']) ? SchoolLevel::fromValueOrLabel($data['school_level']) : null,
                'memorized_hizbs' => $hizbs !== null && $hizbs >= 0 && $hizbs <= 60 ? $hizbs : null,
                'exempt' => $exempt,
                'notes' => $data['notes'],
                'guardian_id' => $guardian?->id,
            ]);

            if ($this->isYes($data['registration_paid'])) {
                $memorizer->payments()->create([
                    'type' => PaymentType::REGISTRATION,
                    'amount' => self::REGISTRATION_FEE,
                    'payment_date' => now(),
                ]);
                $this->registrationPayments++;
            }

            if (! $exempt && $this->isYes($data['october_paid'])) {
                $memorizer->payments()->create([
                    'type' => PaymentType::MONTHLY,
                    'amount' => self::MONTHLY_FEE,
                    'payment_date' => $this->octoberOfSchoolYear(),
                ]);
                $this->monthlyPayments++;
            }

            $this->created++;
        });
    }

    protected function formatPhone(mixed $value): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $value);

        if (str_starts_with($digits, '00212')) {
            $digits = substr($digits, 5);
        } elseif (str_starts_with($digits, '212')) {
            $digits = substr($digits, 3);
        }

        $digits = ltrim($digits, '0');

        if (! preg_match('/^[5-7]\d{8}$/', $digits)) {
            return null;
        }

        return '+212 '.substr($digits, 0, 3).'-'.substr($digits, 3);
    }

    protected function parseDate(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        if (is_numeric($value) && $value > 1000 && $value < 100000) {
            return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value))->toDateString();
        }

        $normalized = str_replace(['.', '-', '\\', ' '], '/', trim((string) $value));

        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $normalized, $parts)) {
            [, $day, $month, $year] = $parts;
        } elseif (preg_match('#^(\d{4})/(\d{1,2})/(\d{1,2})$#', $normalized, $parts)) {
            [, $year, $month, $day] = $parts;
        } else {
            return null;
        }

        if (! checkdate((int) $month, (int) $day, (int) $year) || $year < 1990) {
            return null;
        }

        $date = Carbon::create((int) $year, (int) $month, (int) $day);

        return $date->isPast() ? $date->toDateString() : null;
    }

    protected function octoberOfSchoolYear(): Carbon
    {
        $startYear = now()->month >= 9 ? now()->year : now()->year - 1;

        return Carbon::create($startYear, 10, 1);
    }

    protected function isYes(mixed $value): bool
    {
        return in_array(mb_strtolower((string) $value), ['نعم', 'yes', '1', 'true', 'oui'], true);
    }

    protected function clean(mixed $value): mixed
    {
        return is_string($value) ? (trim($value) === '' ? null : trim($value)) : $value;
    }

    protected function fail(string $file, ?int $row, string $name, string $reason): void
    {
        $this->failures[] = compact('file', 'row', 'name', 'reason');
    }
}
