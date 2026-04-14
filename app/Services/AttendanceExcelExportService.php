<?php

namespace App\Services;

use InvalidArgumentException;
use App\Enums\AttendanceStatus;
use App\Enums\MemorizationScore;
use App\Enums\Troubles;
use App\Models\Attendance;
use App\Models\MemoGroup;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\RichText\Run;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class AttendanceExcelExportService
{
    private const MAX_EXPORT_DAYS = 183;

    /**
     * Columns selected when loading attendance records.
     * Single source of truth used by both download() and downloadAllGroups().
     */
    private const ATTENDANCE_SELECT_COLUMNS = [
        'id',
        'memorizer_id',
        'date',
        'check_in_time',
        'score',
        'notes',
        'custom_note',
        'created_by',
        'absence_justified',
    ];

    public function download(MemoGroup $group, array $options): BinaryFileResponse
    {
        [$dateFrom, $dateTo, $dates] = $this->resolveDateRange(
            $options['date_from'],
            $options['date_to'],
        );
        $sexFilter = $this->normalizeSexFilter($options['sex_filter'] ?? null);

        $group->loadMissing('teacher:id,name,sex');

        $memorizers = $group->memorizers()
            ->with([
                'guardian:id,name,phone',
                'attendances' => fn ($query) => $query
                    ->select(self::ATTENDANCE_SELECT_COLUMNS)
                    ->with('createdBy:id,name')
                    ->whereDate('date', '>=', $dateFrom->toDateString())
                    ->whereDate('date', '<=', $dateTo->toDateString())
                    ->orderBy('date'),
            ])
            ->orderBy('name')
            ->get();

        if (! $this->groupMatchesSexFilter($group, $sexFilter)) {
            $memorizers = $memorizers->take(0);
        }

        $dates = $this->filterDatesForGroup($group, $dates);
        if ($dates->isEmpty()) {
            throw new InvalidArgumentException('لا توجد أيام عمل لهذه المجموعة داخل الفترة المحددة.');
        }
        $this->filterAttendancesToDates($memorizers, $dates);

        if ($memorizers->isEmpty()) {
            throw new InvalidArgumentException('لا يوجد طلاب مطابقون لفلتر الجنس في هذه المجموعة.');
        }

        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        $context = [
            'group' => $group,
            'memorizers' => $memorizers,
            'dates' => $dates,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'include_detail_sheet' => (bool) ($options['include_detail_sheet'] ?? false),
            'include_contact_columns' => (bool) ($options['include_contact_columns'] ?? false),
            'include_student_numbers' => (bool) ($options['include_student_numbers'] ?? false),
            'sex_filter' => $sexFilter,
        ];

        $stats = $this->buildStats($context);
        $context['stats'] = $stats;

        $this->buildSummarySheet($spreadsheet, $context);
        $this->buildMatrixSheet($spreadsheet, $context, 'الحضور والتقييم');

        if ($context['include_detail_sheet']) {
            $this->buildDetailsSheet($spreadsheet, $context);
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'attendance-export-');
        $writer = new Xlsx($spreadsheet);
        $writer->save($tmpFile);

        return response()->download(
            $tmpFile,
            $this->makeFileName($group, $dateFrom, $dateTo),
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        )->deleteFileAfterSend(true)->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $this->makeFileName($group, $dateFrom, $dateTo)
        );
    }

    public function downloadAllGroups(array $options): BinaryFileResponse
    {
        [$dateFrom, $dateTo, $dates] = $this->resolveDateRange(
            $options['date_from'],
            $options['date_to'],
        );
        $sexFilter = $this->normalizeSexFilter($options['sex_filter'] ?? null);

        $groups = MemoGroup::query()
            ->with([
                'teacher:id,name,sex',
                'memorizers' => fn ($query) => $query
                    ->with([
                        'guardian:id,name,phone',
                        'attendances' => fn ($attendanceQuery) => $attendanceQuery
                            ->select(self::ATTENDANCE_SELECT_COLUMNS)
                            ->with('createdBy:id,name')
                            ->whereDate('date', '>=', $dateFrom->toDateString())
                            ->whereDate('date', '<=', $dateTo->toDateString())
                            ->orderBy('date'),
                    ])
                    ->orderBy('name'),
            ])
            ->whereHas('memorizers')
            ->orderBy('name')
            ->get();

        if ($sexFilter !== null) {
            $groups = $groups->filter(fn (MemoGroup $group) => $this->groupMatchesSexFilter($group, $sexFilter))->values();
        }

        if ($groups->isEmpty()) {
            throw new InvalidArgumentException('لا توجد مجموعات تحتوي على طلاب مطابقين لفلتر الجنس حالياً.');
        }

        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        $contexts = $groups->map(function (MemoGroup $group) use ($dateFrom, $dateTo, $dates, $options, $sexFilter) {
            $groupDates = $this->filterDatesForGroup($group, $dates);
            $memorizers = $group->memorizers;
            $this->filterAttendancesToDates($memorizers, $groupDates);

            $context = [
                'group' => $group,
                'memorizers' => $memorizers,
                'dates' => $groupDates,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'include_detail_sheet' => (bool) ($options['include_detail_sheet'] ?? false),
                'include_contact_columns' => (bool) ($options['include_contact_columns'] ?? false),
                'include_student_numbers' => (bool) ($options['include_student_numbers'] ?? false),
                'sex_filter' => $sexFilter,
            ];
            $context['stats'] = $this->buildStats($context);

            return $context;
        })->filter(fn (array $context) => $context['dates']->isNotEmpty())->values();

        if ($contexts->isEmpty()) {
            throw new InvalidArgumentException('لا توجد مجموعات لها أيام عمل ضمن الفترة المحددة.');
        }

        $this->buildAllGroupsSummarySheet($spreadsheet, $contexts, $dateFrom, $dateTo);

        $usedSheetTitles = ['ملخص'];
        if ((bool) ($options['include_detail_sheet'] ?? false)) {
            $usedSheetTitles[] = 'التفاصيل';
        }

        foreach ($contexts as $context) {
            $title = $this->makeUniqueSheetTitle($this->safeSheetTitle($context['group']->name), $usedSheetTitles);
            $this->buildMatrixSheet($spreadsheet, $context, $title);
        }

        if ((bool) ($options['include_detail_sheet'] ?? false)) {
            $this->buildCombinedDetailsSheet($spreadsheet, $contexts);
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'attendance-export-all-groups-');
        $writer = new Xlsx($spreadsheet);
        $writer->save($tmpFile);

        $fileName = sprintf(
            'attendance-grades-all-groups-%s-to-%s.xlsx',
            $dateFrom->format('Y-m-d'),
            $dateTo->format('Y-m-d'),
        );

        return response()->download(
            $tmpFile,
            $fileName,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        )->deleteFileAfterSend(true)->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $fileName
        );
    }

    public function validateOptions(array $options): void
    {
        $this->resolveDateRange($options['date_from'], $options['date_to']);
    }

    // ─── Date Helpers ──────────────────────────────────────────────────

    private function resolveDateRange(string $from, string $to): array
    {
        $dateFrom = Carbon::parse($from)->startOfDay();
        $dateTo = Carbon::parse($to)->startOfDay();

        if ($dateFrom->greaterThan($dateTo)) {
            throw new InvalidArgumentException('يجب أن يكون تاريخ البداية قبل أو يساوي تاريخ النهاية.');
        }

        $dates = collect(CarbonPeriod::create($dateFrom, $dateTo))
            ->map(fn ($date) => Carbon::parse($date)->startOfDay())
            ->values();

        if ($dates->count() > self::MAX_EXPORT_DAYS) {
            throw new InvalidArgumentException('لا يمكن تصدير أكثر من 183 يوماً في ملف واحد.');
        }

        return [$dateFrom, $dateTo, $dates];
    }

    // ─── Stats ─────────────────────────────────────────────────────────

    private function buildStats(array $context): array
    {
        $attendanceRows = $context['memorizers']
            ->flatMap(fn ($memorizer) => $memorizer->attendances)
            ->values();

        $totalSlots = $context['memorizers']->count() * $context['dates']->count();
        $presentCount = $attendanceRows->filter(fn (Attendance $a) => $a->isPresent())->count();

        $absentJustifiedCount = $attendanceRows
            ->filter(fn (Attendance $a) => $a->isJustifiedAbsence())
            ->count();

        $absentUnjustifiedCount = $attendanceRows
            ->filter(fn (Attendance $a) => $a->isAbsent() && ! $a->isJustifiedAbsence())
            ->count();

        $absentCount = $absentJustifiedCount + $absentUnjustifiedCount;

        $unmarkedCount = max($totalSlots - $attendanceRows->count(), 0);
        $presentWithoutScoreCount = $attendanceRows
            ->filter(fn (Attendance $a) => $a->isPresent() && $a->score === null)
            ->count();

        $scoreDistribution = collect(MemorizationScore::cases())
            ->mapWithKeys(fn (MemorizationScore $score) => [
                $score->getLabel() => $attendanceRows->where('score', $score)->count(),
            ])
            ->filter(fn (int $count) => $count > 0)
            ->all();

        $behaviorDistribution = $attendanceRows
            ->flatMap(fn (Attendance $attendance) => $attendance->notes ?? [])
            ->countBy()
            ->mapWithKeys(function (int $count, string $value) {
                return [Troubles::tryFrom($value)?->getLabel() ?? $value => $count];
            })
            ->sortDesc()
            ->all();

        $attendancePercentage = $totalSlots > 0
            ? round(($presentCount / $totalSlots) * 100, 1)
            : 0;

        return [
            'attendance_rows' => $attendanceRows,
            'present_count' => $presentCount,
            'absent_count' => $absentCount,
            'absent_justified_count' => $absentJustifiedCount,
            'absent_unjustified_count' => $absentUnjustifiedCount,
            'unmarked_count' => $unmarkedCount,
            'present_without_score_count' => $presentWithoutScoreCount,
            'attendance_percentage' => $attendancePercentage,
            'score_distribution' => $scoreDistribution,
            'behavior_distribution' => $behaviorDistribution,
        ];
    }

    // ─── Summary Sheet ─────────────────────────────────────────────────

    private function buildSummarySheet(Spreadsheet $spreadsheet, array $context): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('ملخص');
        $sheet->setRightToLeft(true);

        $sheet->mergeCells('A1:F1');
        $sheet->setCellValue('A1', 'تقرير الحضور والتقييم');
        $sheet->mergeCells('A2:F2');
        $sheet->setCellValue(
            'A2',
            sprintf(
                'المجموعة: %s | الأستاذ: %s',
                $context['group']->name,
                $context['group']->teacher?->name ?? 'غير محدد'
            )
        );
        $sheet->mergeCells('A3:F3');
        $sheet->setCellValue(
            'A3',
            sprintf(
                'الفترة: %s إلى %s | تاريخ التصدير: %s',
                $context['date_from']->format('Y-m-d'),
                $context['date_to']->format('Y-m-d'),
                now()->format('Y-m-d H:i')
            )
        );

        $sheet->fromArray([
            ['عدد الطلاب', $context['memorizers']->count(), 'عدد الأيام', $context['dates']->count()],
            ['إجمالي الحضور في الفترة', $context['stats']['present_count'], 'إجمالي الغياب في الفترة', $context['stats']['absent_count']],
            ['غياب مبرر', $context['stats']['absent_justified_count'], 'غياب غير مبرر', $context['stats']['absent_unjustified_count']],
            ['إجمالي غير المسجل في الفترة', $context['stats']['unmarked_count'], 'نسبة الحضور', $context['stats']['attendance_percentage'] . '%'],
            ['حاضر بدون تقييم', $context['stats']['present_without_score_count'], 'عدد السجلات', $context['stats']['attendance_rows']->count()],
        ], null, 'A5');

        $sheet->fromArray([['توزيع التقييم', 'العدد']], null, 'A12');
        $scoreRows = collect($context['stats']['score_distribution'])
            ->map(fn ($count, $label) => [$label, $count])
            ->values()
            ->all();
        if ($scoreRows === []) {
            $scoreRows = [['لا توجد تقييمات', 0]];
        }
        $sheet->fromArray($scoreRows, null, 'A13');

        $sheet->fromArray([['ملاحظات السلوك', 'العدد']], null, 'D12');
        $behaviorRows = collect($context['stats']['behavior_distribution'])
            ->map(fn ($count, $label) => [$label, $count])
            ->values()
            ->all();
        if ($behaviorRows === []) {
            $behaviorRows = [['لا توجد ملاحظات', 0]];
        }
        $sheet->fromArray($behaviorRows, null, 'D13');

        $legendStart = 14 + max(count($scoreRows), count($behaviorRows));
        $sheet->fromArray([
            ['الدليل', 'المعنى'],
            ['أخضر', 'حاضر'],
            ['أحمر', 'غائب غير مبرر'],
            ['برتقالي', 'غائب مبرر'],
            ['رمادي', 'غير مسجل'],
            ['ألوان التقييم', 'حاضر مع تقييم'],
        ], null, "A{$legendStart}");

        $this->styleSummarySheet($sheet, $scoreRows, $behaviorRows, $legendStart);
    }

    // ─── Matrix Sheet ──────────────────────────────────────────────────

    private function buildMatrixSheet(Spreadsheet $spreadsheet, array $context, ?string $sheetTitle = null): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle($sheetTitle ?? 'الحضور والتقييم');
        $sheet->setRightToLeft(true);

        $headers = ['#'];
        if ($context['include_student_numbers']) {
            $headers[] = 'رقم الطالب';
        }
        $headers[] = 'اسم الطالب';
        if ($context['include_contact_columns']) {
            $headers[] = 'الهاتف';
            $headers[] = 'اسم الولي';
            $headers[] = 'هاتف الولي';
        }

        foreach ($context['dates'] as $date) {
            $headers[] = $this->formatArabicWeekday($date) . "\n" . $date->format('d/m');
        }

        $sheet->fromArray([$headers], null, 'A1');

        $attendanceMap = $this->buildAttendanceMap($context['memorizers']);
        $baseColumnCount = count($headers) - $context['dates']->count();
        $sheet->freezePane(Coordinate::stringFromColumnIndex($baseColumnCount + 1) . '2');

        foreach ($context['memorizers']->values() as $index => $memorizer) {
            $rowIndex = $index + 2;
            $row = [
                $index + 1,
            ];

            if ($context['include_student_numbers']) {
                $row[] = $memorizer->number;
            }

            $row[] = $memorizer->name;

            if ($context['include_contact_columns']) {
                $row[] = $memorizer->phone ?: ($memorizer->guardian?->phone ?? '—');
                $row[] = $memorizer->guardian?->name ?? '—';
                $row[] = $memorizer->guardian?->phone ?? '—';
            }

            foreach ($context['dates'] as $date) {
                /** @var Attendance|null $attendance */
                $attendance = $attendanceMap[$memorizer->id][$date->toDateString()] ?? null;
                $row[] = $this->makeMatrixCellValue($attendance);
            }

            $sheet->fromArray([$row], null, "A{$rowIndex}");

            for ($dateOffset = 0; $dateOffset < $context['dates']->count(); $dateOffset++) {
                $columnIndex = $baseColumnCount + $dateOffset + 1;
                $cell = Coordinate::stringFromColumnIndex($columnIndex) . $rowIndex;
                $attendance = $attendanceMap[$memorizer->id][$context['dates'][$dateOffset]->toDateString()] ?? null;
                $this->applyMatrixStatusStyle($sheet, $cell, $attendance);
            }
        }

        $lastColumn = Coordinate::stringFromColumnIndex(count($headers));
        $lastRow = max($context['memorizers']->count() + 1, 2);

        $sheet->getStyle("A1:{$lastColumn}{$lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("A1:{$lastColumn}{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle("A1:{$lastColumn}1")->getAlignment()->setWrapText(true)->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $identityLastColumn = 1 + ($context['include_student_numbers'] ? 1 : 0) + 1;
        $sheet->getStyle('A2:' . Coordinate::stringFromColumnIndex($identityLastColumn) . $lastRow)
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0F766E']],
        ]);

        if ($lastRow >= 2) {
            for ($row = 2; $row <= $lastRow; $row++) {
                if ($row % 2 === 0) {
                    $sheet->getStyle("A{$row}:" . Coordinate::stringFromColumnIndex($baseColumnCount) . $row)->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()
                        ->setRGB('F8FAFC');
                }
            }
        }

        foreach (range(1, count($headers)) as $columnIndex) {
            $column = Coordinate::stringFromColumnIndex($columnIndex);
            $sheet->getColumnDimension($column)->setAutoSize(false);
            $sheet->getColumnDimension($column)->setWidth($columnIndex <= $baseColumnCount ? 18 : 15);
        }

        $sheet->getStyle("A1:{$lastColumn}{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->setAutoFilter("A1:{$lastColumn}{$lastRow}");
    }

    // ─── Details Sheet (Single Group) ──────────────────────────────────

    private function buildDetailsSheet(Spreadsheet $spreadsheet, array $context): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('التفاصيل');
        $sheet->setRightToLeft(true);
        $sheet->freezePane('A2');

        $headers = [
            'التاريخ',
            'اليوم',
            'رقم الطالب',
            'اسم الطالب',
        ];

        if ($context['include_contact_columns']) {
            $headers[] = 'الهاتف';
            $headers[] = 'اسم الولي';
            $headers[] = 'هاتف الولي';
        }

        $headers = array_merge($headers, [
            'الحالة',
            'وقت الحضور',
            'التقييم',
            'ملاحظات السلوك',
            'ملاحظة إضافية',
            'سجل بواسطة',
        ]);
        $sheet->fromArray([$headers], null, 'A1');

        $rowIndex = 2;

        foreach ($context['memorizers'] as $memorizer) {
            foreach ($memorizer->attendances as $attendance) {
                $status = AttendanceStatus::resolve($attendance);

                $row = [
                    $attendance->date->format('Y-m-d'),
                    $this->formatArabicWeekday($attendance->date),
                    $memorizer->number,
                    $memorizer->name,
                ];

                if ($context['include_contact_columns']) {
                    $row[] = $memorizer->phone ?: ($memorizer->guardian?->phone ?? '—');
                    $row[] = $memorizer->guardian?->name ?? '—';
                    $row[] = $memorizer->guardian?->phone ?? '—';
                }

                $row = array_merge($row, [
                    $status->getExportLabel(),
                    $attendance->isPresent() ? Carbon::parse($attendance->check_in_time)->format('H:i') : '—',
                    $attendance->score?->getLabel() ?? '—',
                    $this->formatTroubleLabels($attendance),
                    $attendance->custom_note ?: '—',
                    $attendance->createdBy?->name ?? '—',
                ]);

                $sheet->fromArray([$row], null, "A{$rowIndex}");
                $rowIndex++;
            }
        }

        if ($rowIndex === 2) {
            $sheet->setCellValue('A2', 'لا توجد سجلات حضور في الفترة المحددة.');
            $rowIndex = 3;
        }

        $lastColumn = Coordinate::stringFromColumnIndex(count($headers));
        $lastRow = $rowIndex - 1;

        $sheet->getStyle("A1:{$lastColumn}{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1D4ED8']],
        ]);
        $sheet->getStyle("A1:{$lastColumn}{$lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("A1:{$lastColumn}{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $nameColIndex = $context['include_contact_columns'] ? 4 : 4;
        $nameCol = Coordinate::stringFromColumnIndex($nameColIndex);
        $sheet->getStyle("{$nameCol}2:{$nameCol}{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        // Wrap text on behavior/note columns (last 3 before 'سجل بواسطة')
        $behaviorColStart = Coordinate::stringFromColumnIndex(count($headers) - 2);
        $noteColEnd = Coordinate::stringFromColumnIndex(count($headers) - 1);
        $sheet->getStyle("{$behaviorColStart}2:{$noteColEnd}{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT)->setWrapText(true);

        for ($row = 2; $row <= $lastRow; $row++) {
            if ($row % 2 === 0) {
                $sheet->getStyle("A{$row}:{$lastColumn}{$row}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()
                    ->setRGB('F8FAFC');
            }
        }

        foreach (range(1, count($headers)) as $columnIndex) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($columnIndex))->setAutoSize(true);
        }

        $sheet->setAutoFilter("A1:{$lastColumn}{$lastRow}");
    }

    // ─── All Groups Summary Sheet ──────────────────────────────────────

    private function buildAllGroupsSummarySheet(
        Spreadsheet $spreadsheet,
        Collection $contexts,
        Carbon $dateFrom,
        Carbon $dateTo
    ): void {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('ملخص');
        $sheet->setRightToLeft(true);

        $sheet->mergeCells('A1:J1');
        $sheet->setCellValue('A1', 'تقرير الحضور والتقييم لجميع المجموعات');
        $sheet->mergeCells('A2:J2');
        $sheet->setCellValue(
            'A2',
            sprintf(
                'الفترة: %s إلى %s | تاريخ التصدير: %s',
                $dateFrom->format('Y-m-d'),
                $dateTo->format('Y-m-d'),
                now()->format('Y-m-d H:i')
            )
        );

        // ── Main table header (row 4) ─────────────────────────────────
        $sheet->fromArray([[
            'المجموعة',
            'الأستاذ',
            'عدد الطلاب',
            'عدد الأيام',
            'إجمالي الحضور',
            'نسبة الحضور',
            'غياب مبرر',
            'غياب غير مبرر',
            'إجمالي الغياب',
            'حاضر بدون تقييم',
        ]], null, 'A4');

        $row = 5;
        foreach ($contexts as $context) {
            $sheet->fromArray([[
                $context['group']->name,
                $context['group']->teacher?->name ?? 'غير محدد',
                $context['memorizers']->count(),
                $context['dates']->count(),
                $context['stats']['present_count'],
                $context['stats']['attendance_percentage'] . '%',
                $context['stats']['absent_justified_count'],
                $context['stats']['absent_unjustified_count'],
                $context['stats']['absent_count'],
                $context['stats']['present_without_score_count'],
            ]], null, "A{$row}");
            $row++;
        }

        $tableLastRow = max($row - 1, 4);

        // ── Per-group breakdown section ───────────────────────────────
        $breakdownStart = $tableLastRow + 2;
        $sheet->mergeCells("A{$breakdownStart}:J{$breakdownStart}");
        $sheet->setCellValue("A{$breakdownStart}", 'تفصيل التقييمات والملاحظات السلوكية لكل مجموعة');
        $sheet->getStyle("A{$breakdownStart}:J{$breakdownStart}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0F766E']],
        ]);

        $detailRow = $breakdownStart + 1;
        foreach ($contexts as $context) {
            $groupName = $context['group']->name;
            $teacherName = $context['group']->teacher?->name ?? 'غير محدد';

            // Group sub-header
            $sheet->mergeCells("A{$detailRow}:J{$detailRow}");
            $sheet->setCellValue("A{$detailRow}", "{$groupName} — {$teacherName}");
            $sheet->getStyle("A{$detailRow}:J{$detailRow}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1D4ED8']],
            ]);
            $detailRow++;

            // Score distribution (left) + Behavior distribution (right)
            $sheet->fromArray([['توزيع التقييم', 'العدد']], null, "A{$detailRow}");
            $sheet->getStyle("A{$detailRow}:B{$detailRow}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '059669']],
            ]);
            $sheet->fromArray([['ملاحظات السلوك', 'العدد']], null, "D{$detailRow}");
            $sheet->getStyle("D{$detailRow}:E{$detailRow}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D97706']],
            ]);
            $detailRow++;

            $scoreRows = collect($context['stats']['score_distribution'])
                ->map(fn ($count, $label) => [$label, $count])
                ->values()
                ->all();
            if ($scoreRows === []) {
                $scoreRows = [['لا توجد تقييمات', 0]];
            }

            $behaviorRows = collect($context['stats']['behavior_distribution'])
                ->map(fn ($count, $label) => [$label, $count])
                ->values()
                ->all();
            if ($behaviorRows === []) {
                $behaviorRows = [['لا توجد ملاحظات', 0]];
            }

            $maxRows = max(count($scoreRows), count($behaviorRows));
            for ($i = 0; $i < $maxRows; $i++) {
                $sheet->fromArray([$scoreRows[$i] ?? ['', '']], null, "A{$detailRow}");
                $sheet->fromArray([$behaviorRows[$i] ?? ['', '']], null, "D{$detailRow}");
                $detailRow++;
            }

            $detailRow++; // spacer between groups
        }

        // ── Styling ───────────────────────────────────────────────────
        $lastCol = 'J';
        $lastRow = max($detailRow - 1, 4);

        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
            'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0F766E']],
        ]);
        $sheet->getStyle("A2:{$lastCol}2")->applyFromArray([
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'ECFDF5']],
        ]);
        $sheet->getStyle("A4:{$lastCol}{$tableLastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle("A4:{$lastCol}4")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1D4ED8']],
        ]);

        for ($currentRow = 5; $currentRow <= $tableLastRow; $currentRow++) {
            if ($currentRow % 2 === 1) {
                $sheet->getStyle("A{$currentRow}:{$lastCol}{$currentRow}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()
                    ->setRGB('F8FAFC');
            }
        }

        foreach (range(1, 10) as $columnIndex) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($columnIndex))->setAutoSize(true);
        }
    }

    // ─── Combined Details Sheet (All Groups) ───────────────────────────

    private function buildCombinedDetailsSheet(Spreadsheet $spreadsheet, Collection $contexts): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('التفاصيل');
        $sheet->setRightToLeft(true);
        $sheet->freezePane('A2');

        $includeContact = $contexts->first()['include_contact_columns'] ?? false;

        $headers = [
            'المجموعة',
            'التاريخ',
            'اليوم',
            'رقم الطالب',
            'اسم الطالب',
        ];

        if ($includeContact) {
            $headers[] = 'الهاتف';
            $headers[] = 'اسم الولي';
            $headers[] = 'هاتف الولي';
        }

        $headers = array_merge($headers, [
            'الحالة',
            'وقت الحضور',
            'التقييم',
            'ملاحظات السلوك',
            'ملاحظة إضافية',
            'سجل بواسطة',
        ]);
        $sheet->fromArray([$headers], null, 'A1');

        $rowIndex = 2;
        foreach ($contexts as $context) {
            foreach ($context['memorizers'] as $memorizer) {
                foreach ($memorizer->attendances as $attendance) {
                    $status = AttendanceStatus::resolve($attendance);

                    $row = [
                        $context['group']->name,
                        $attendance->date->format('Y-m-d'),
                        $this->formatArabicWeekday($attendance->date),
                        $memorizer->number,
                        $memorizer->name,
                    ];

                    if ($includeContact) {
                        $row[] = $memorizer->phone ?: ($memorizer->guardian?->phone ?? '—');
                        $row[] = $memorizer->guardian?->name ?? '—';
                        $row[] = $memorizer->guardian?->phone ?? '—';
                    }

                    $row = array_merge($row, [
                        $status->getExportLabel(),
                        $attendance->isPresent() ? Carbon::parse($attendance->check_in_time)->format('H:i') : '—',
                        $attendance->score?->getLabel() ?? '—',
                        $this->formatTroubleLabels($attendance),
                        $attendance->custom_note ?: '—',
                        $attendance->createdBy?->name ?? '—',
                    ]);

                    $sheet->fromArray([$row], null, "A{$rowIndex}");
                    $rowIndex++;
                }
            }
        }

        if ($rowIndex === 2) {
            $sheet->setCellValue('A2', 'لا توجد سجلات حضور في الفترة المحددة.');
            $rowIndex = 3;
        }

        $lastColumn = Coordinate::stringFromColumnIndex(count($headers));
        $lastRow = $rowIndex - 1;
        $sheet->getStyle("A1:{$lastColumn}{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1D4ED8']],
        ]);
        $sheet->getStyle("A1:{$lastColumn}{$lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("A1:{$lastColumn}{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $nameColIndex = $includeContact ? 5 : 5;
        $nameCol = Coordinate::stringFromColumnIndex($nameColIndex);
        $sheet->getStyle("{$nameCol}2:{$nameCol}{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        $behaviorColStart = Coordinate::stringFromColumnIndex(count($headers) - 2);
        $noteColEnd = Coordinate::stringFromColumnIndex(count($headers) - 1);
        $sheet->getStyle("{$behaviorColStart}2:{$noteColEnd}{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT)->setWrapText(true);

        foreach (range(1, count($headers)) as $columnIndex) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($columnIndex))->setAutoSize(true);
        }

        $sheet->setAutoFilter("A1:{$lastColumn}{$lastRow}");
    }

    // ─── Summary Sheet Styling ─────────────────────────────────────────

    private function styleSummarySheet($sheet, array $scoreRows, array $behaviorRows, int $legendStart): void
    {
        $sheet->getStyle('A1:F1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0F766E']],
        ]);

        $sheet->getStyle('A2:F3')->applyFromArray([
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'ECFDF5']],
        ]);

        $sheet->getStyle('A5:D9')->applyFromArray([
            'font' => ['bold' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);

        $scoreLastRow = 12 + count($scoreRows);
        $behaviorLastRow = 12 + count($behaviorRows);

        $sheet->getStyle("A12:B{$scoreLastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle("D12:E{$behaviorLastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle('A12:B12')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1D4ED8']],
        ]);
        $sheet->getStyle('D12:E12')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D97706']],
        ]);

        $legendEnd = $legendStart + 5;
        $sheet->getStyle("A{$legendStart}:B{$legendEnd}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle("A{$legendStart}:B{$legendStart}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '334155']],
        ]);

        // Legend color swatches
        $sheet->getStyle('A' . ($legendStart + 1) . ':B' . ($legendStart + 1))->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(AttendanceStatus::PRESENT->getExportFillColor());
        $sheet->getStyle('A' . ($legendStart + 2) . ':B' . ($legendStart + 2))->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(AttendanceStatus::ABSENT_UNJUSTIFIED->getExportFillColor());
        $sheet->getStyle('A' . ($legendStart + 3) . ':B' . ($legendStart + 3))->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(AttendanceStatus::ABSENT_JUSTIFIED->getExportFillColor());
        $sheet->getStyle('A' . ($legendStart + 4) . ':B' . ($legendStart + 4))->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(AttendanceStatus::UNMARKED->getExportFillColor());

        foreach (range('A', 'F') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
    }

    // ─── Matrix Cell Helpers ───────────────────────────────────────────

    private function buildAttendanceMap(Collection $memorizers): array
    {
        $map = [];

        foreach ($memorizers as $memorizer) {
            foreach ($memorizer->attendances as $attendance) {
                $map[$memorizer->id][$attendance->date->format('Y-m-d')] = $attendance;
            }
        }

        return $map;
    }

    /**
     * Generate cell value for the matrix sheet. Uses AttendanceStatus for status labels.
     */
    private function makeMatrixCellValue(?Attendance $attendance): string|RichText
    {
        $status = AttendanceStatus::resolve($attendance);

        if ($status !== AttendanceStatus::PRESENT) {
            return $status->getExportLabel();
        }

        if (! $attendance->score instanceof MemorizationScore) {
            return $status->getExportLabel();
        }

        $richText = new RichText();
        $statusRun = $richText->createTextRun('حاضر');
        $statusRun->getFont()->setBold(true);
        $richText->createText("\n");

        $scoreRun = $richText->createTextRun($attendance->score->getLabel());
        $scoreRun->getFont()->getColor()->setRGB($this->scorePalette($attendance->score)[1]);
        $scoreRun->getFont()->setBold(true);

        return $richText;
    }

    /**
     * Apply fill/font styling to a matrix cell. Uses AttendanceStatus for colors.
     */
    private function applyMatrixStatusStyle($sheet, string $cell, ?Attendance $attendance): void
    {
        $status = AttendanceStatus::resolve($attendance);

        $fill = $status->getExportFillColor();
        $font = $status->getExportFontColor();

        $sheet->getStyle($cell)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => $font]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $fill]],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
        ]);
    }

    private function scorePalette(MemorizationScore $score): array
    {
        return match ($score) {
            MemorizationScore::EXCELLENT => ['A7F3D0', '065F46'],
            MemorizationScore::GOOD => ['BBF7D0', '166534'],
            MemorizationScore::VERY_GOOD => ['BFDBFE', '1D4ED8'],
            MemorizationScore::FAIR => ['FDE68A', '92400E'],
            MemorizationScore::ACCEPTABLE => ['E5E7EB', '374151'],
            MemorizationScore::POOR => ['FCA5A5', '991B1B'],
            MemorizationScore::NOT_MEMORIZED => ['FDA4AF', '9F1239'],
        };
    }

    // ─── Formatting Helpers ────────────────────────────────────────────

    private function formatTroubleLabels(Attendance $attendance): string
    {
        if (! $attendance->notes || count($attendance->notes) === 0) {
            return '—';
        }

        return collect($attendance->notes)
            ->map(fn (string $value) => Troubles::tryFrom($value)?->getLabel() ?? $value)
            ->implode('، ');
    }

    private function formatArabicWeekday(Carbon $date): string
    {
        return match ($date->dayOfWeek) {
            Carbon::SUNDAY => 'الأحد',
            Carbon::MONDAY => 'الاثنين',
            Carbon::TUESDAY => 'الثلاثاء',
            Carbon::WEDNESDAY => 'الأربعاء',
            Carbon::THURSDAY => 'الخميس',
            Carbon::FRIDAY => 'الجمعة',
            Carbon::SATURDAY => 'السبت',
        };
    }

    // ─── File / Sheet Name Helpers ─────────────────────────────────────

    private function filterDatesForGroup(MemoGroup $group, Collection $dates): Collection
    {
        $workingDays = collect($group->days ?? [])
            ->filter()
            ->map(fn ($day) => strtolower((string) $day))
            ->values();

        if ($workingDays->isEmpty()) {
            return $dates->values();
        }

        return $dates
            ->filter(fn (Carbon $date) => $workingDays->contains(strtolower($date->englishDayOfWeek)))
            ->values();
    }

    private function filterAttendancesToDates(Collection $memorizers, Collection $dates): void
    {
        $allowedDates = $dates->map(fn (Carbon $date) => $date->toDateString())->all();

        foreach ($memorizers as $memorizer) {
            $memorizer->setRelation(
                'attendances',
                $memorizer->attendances->filter(
                    fn (Attendance $attendance) => in_array($attendance->date->format('Y-m-d'), $allowedDates, true)
                )->values()
            );
        }
    }

    private function makeFileName(MemoGroup $group, Carbon $dateFrom, Carbon $dateTo): string
    {
        $slug = Str::slug($group->name);
        if ($slug === '') {
            $slug = 'memo-group-' . $group->id;
        }

        return sprintf(
            'attendance-grades-%s-%s-to-%s.xlsx',
            $slug,
            $dateFrom->format('Y-m-d'),
            $dateTo->format('Y-m-d'),
        );
    }

    private function safeSheetTitle(string $title): string
    {
        $clean = preg_replace('/[\\\\\\/?*:\\[\\]]/', '-', $title) ?? $title;
        $clean = trim($clean);

        if ($clean === '') {
            $clean = 'مجموعة';
        }

        return Str::limit($clean, 31, '');
    }

    private function makeUniqueSheetTitle(string $baseTitle, array &$usedTitles): string
    {
        $title = $baseTitle;
        $suffix = 2;

        while (in_array($title, $usedTitles, true)) {
            $candidate = Str::limit($baseTitle, 28, '') . '-' . $suffix;
            $title = Str::limit($candidate, 31, '');
            $suffix++;
        }

        $usedTitles[] = $title;

        return $title;
    }

    private function normalizeSexFilter(?string $sexFilter): ?string
    {
        return in_array($sexFilter, ['male', 'female'], true) ? $sexFilter : null;
    }

    private function groupMatchesSexFilter(MemoGroup $group, ?string $sexFilter): bool
    {
        if ($sexFilter === null) {
            return true;
        }

        return strtolower((string) ($group->teacher?->sex ?? '')) === $sexFilter;
    }
}
