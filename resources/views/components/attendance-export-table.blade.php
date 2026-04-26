@php
    use App\Enums\AttendanceStatus;
    use App\Enums\MemorizationScore;
    use Carbon\Carbon;

    $dateObj = Carbon::parse($date ?? now());

    $toEnglishDigits = fn (string $s) => strtr($s, [
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
    ]);

    $hijriDate = '';
    if (class_exists(\IntlDateFormatter::class)) {
        $hijriFmt = new \IntlDateFormatter(
            'ar_SA@calendar=islamic',
            \IntlDateFormatter::LONG,
            \IntlDateFormatter::NONE,
            'Asia/Riyadh',
            \IntlDateFormatter::TRADITIONAL,
            'd MMMM y'
        );
        $hijriDate = $toEnglishDigits($hijriFmt->format($dateObj->copy()->setTime(12, 0))) . ' هـ';
    }

    $gregDate = $toEnglishDigits($dateObj->locale('ar')->translatedFormat('l j F Y'));

    $dayNamesAr = [
        'sunday' => 'الأحد',
        'monday' => 'الإثنين',
        'tuesday' => 'الثلاثاء',
        'wednesday' => 'الأربعاء',
        'thursday' => 'الخميس',
        'friday' => 'الجمعة',
        'saturday' => 'السبت',
    ];
    $daysLabel = collect($group->days ?? [])
        ->map(fn ($d) => $dayNamesAr[strtolower($d)] ?? '')
        ->filter()
        ->implode(' و');

    $presencePercentage = $presencePercentage ?? 0;
    $chunks = $memorizers->chunk(25);
    $totalPages = $chunks->count();

    $exportStamp = $toEnglishDigits(
        now()->locale('ar')->translatedFormat('j F Y') . ' · ' . now()->format('H:i')
    );
@endphp

<div class="mushaf-export" style="direction: rtl;">
    <style>
        .mushaf-export {
            --cream: #faf4e3;
            --cream-2: #f5ecd4;
            --emerald: #0d5d3f;
            --emerald-ink: #0a4a32;
            --gold: #b8860b;
            --gold-2: #8b6914;
            --gold-soft: #d4c088;
            --ink: #2d1f0f;
            --row-border: #e7d9a8;
            font-family: 'Almarai', 'Tajawal', sans-serif;
            color: var(--ink);
        }

        .mushaf-export .report-card {
            background: var(--cream);
            border: 2px solid var(--emerald);
            box-shadow: inset 0 0 0 4px var(--cream), inset 0 0 0 5px var(--gold);
            border-radius: 4px;
            overflow: hidden;
            padding-bottom: 14px;
        }

        .mushaf-export .bismillah-band {
            background-color: var(--emerald);
            background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='26' height='26' viewBox='0 0 26 26'><path d='M13 3 L15.5 10.5 L23 13 L15.5 15.5 L13 23 L10.5 15.5 L3 13 L10.5 10.5 Z' fill='%23b8860b' opacity='.22'/></svg>");
            color: var(--cream);
            padding: 18px 20px 22px;
            text-align: center;
            border-bottom: 4px double var(--gold);
            margin-bottom: 14px;
        }
        .mushaf-export .masthead-title {
            font-family: 'Amiri', 'Almarai', serif;
            font-size: 28px;
            font-weight: 700;
            margin: 0;
            letter-spacing: 1.5px;
            color: var(--cream);
            line-height: 1.2;
        }
        .mushaf-export .hijri {
            font-family: 'Reem Kufi', 'Almarai', sans-serif;
            font-size: 14px;
            margin-top: 8px;
            color: #e7d9a8;
            letter-spacing: 2px;
        }
        .mushaf-export .greg {
            font-size: 12px;
            margin-top: 3px;
            color: #d4c088;
        }
        .mushaf-export .page-num {
            margin-top: 10px;
            display: inline-block;
            background: rgba(184, 134, 11, 0.25);
            border: 1px solid var(--gold);
            padding: 2px 10px;
            border-radius: 2px;
            font-family: 'Reem Kufi', sans-serif;
            font-size: 11px;
            letter-spacing: 1px;
            color: var(--cream);
        }

        .mushaf-export .group-card {
            margin: 0 20px 14px;
            background: linear-gradient(180deg, #ffffff 0%, var(--cream-2) 100%);
            border: 1px solid var(--gold);
            padding: 12px 14px;
            text-align: center;
            border-radius: 4px;
            position: relative;
        }
        .mushaf-export .group-card::before,
        .mushaf-export .group-card::after {
            content: '❋';
            color: var(--gold);
            position: absolute;
            top: 6px;
            font-size: 14px;
            line-height: 1;
        }
        .mushaf-export .group-card::before { right: 10px; }
        .mushaf-export .group-card::after { left: 10px; }
        .mushaf-export .group-name {
            font-family: 'Amiri', 'Almarai', serif;
            font-weight: 700;
            color: var(--emerald);
            font-size: 20px;
            line-height: 1.3;
        }
        .mushaf-export .days {
            font-size: 13px;
            color: var(--gold-2);
            margin-top: 3px;
        }
        .mushaf-export .percent {
            display: inline-block;
            background: var(--emerald);
            color: var(--cream);
            padding: 4px 18px;
            border-radius: 20px;
            font-weight: 800;
            font-size: 14px;
            margin-top: 8px;
            border: 1px solid var(--gold);
            font-family: 'Reem Kufi', 'Almarai', sans-serif;
            letter-spacing: 1px;
        }

        .mushaf-export .report-table {
            width: calc(100% - 40px);
            margin: 0 20px;
            border-collapse: collapse;
            font-size: 14px;
        }
        .mushaf-export .report-table th {
            background: var(--emerald);
            color: var(--cream);
            padding: 10px 6px;
            font-family: 'Reem Kufi', 'Almarai', sans-serif;
            border: 1px solid var(--gold);
            letter-spacing: 1px;
            font-weight: 600;
            font-size: 13px;
        }
        .mushaf-export .report-table td {
            border: 1px solid var(--row-border);
            background: rgba(255, 255, 255, 0.55);
            text-align: center;
            padding: 8px 6px;
            vertical-align: middle;
        }
        .mushaf-export .report-table tr:nth-child(even) td {
            background: rgba(13, 93, 63, 0.04);
        }
        .mushaf-export .report-table td.name {
            text-align: right;
            font-weight: 600;
            color: var(--ink);
            padding-right: 14px;
        }
        .mushaf-export .report-table td.idx {
            color: var(--gold-2);
            font-family: 'Reem Kufi', 'Almarai', sans-serif;
            width: 36px;
        }

        .mushaf-export .st-present { color: var(--emerald); font-weight: 700; }
        .mushaf-export .st-absent { color: var(--gold-2); }
        .mushaf-export .st-mark {
            display: inline-block;
            font-weight: 800;
            margin-left: 4px;
        }
        .mushaf-export .st-mark.present { color: var(--emerald); }
        .mushaf-export .st-mark.absent { color: var(--gold); }

        .mushaf-export .sc-excellent { color: #065f46; font-weight: 800; }
        .mushaf-export .sc-good { color: #0369a1; font-weight: 600; }
        .mushaf-export .sc-fair { color: var(--gold-2); font-weight: 600; }
        .mushaf-export .sc-poor { color: #991b1b; font-weight: 600; }
        .mushaf-export .sc-dash { color: #b4a576; }
        .mushaf-export .sc-star {
            display: inline-block;
            color: var(--gold);
            margin-left: 4px;
            font-weight: 700;
        }

        .mushaf-export .footer-signoff {
            text-align: center;
            margin: 14px 20px 0;
            padding-top: 10px;
            border-top: 1px dotted var(--gold);
            font-size: 12px;
            color: var(--gold-2);
            font-family: 'Reem Kufi', 'Almarai', sans-serif;
            letter-spacing: 1px;
        }
        .mushaf-export .footer-signoff .stamp {
            display: block;
            margin-top: 3px;
        }

        .mushaf-export .table-page + .table-page {
            margin-top: 24px;
        }
    </style>

    @foreach ($chunks as $pageIndex => $chunk)
        <div class="table-page" data-page="{{ $pageIndex + 1 }}">
            <div class="report-card">
                <div class="bismillah-band">
                    <h1 class="masthead-title">تقرير الحضور والتقييم</h1>
                    @if ($hijriDate)
                        <div class="hijri">{{ $hijriDate }}</div>
                    @endif
                    <div class="greg">{{ $gregDate }}</div>
                    @if ($totalPages > 1)
                        <div class="page-num">صفحة {{ $pageIndex + 1 }} من {{ $totalPages }}</div>
                    @endif
                </div>

                <div class="group-card">
                    <div class="group-name">{{ $group->name }}</div>
                    @if ($daysLabel)
                        <div class="days">{{ $daysLabel }}</div>
                    @endif
                    <div class="percent">نسبة الحضور {{ $presencePercentage }}%</div>
                </div>

                <table class="report-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>الإسم</th>
                            <th>الحضور</th>
                            <th>التقييم</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($chunk as $memorizer)
                            @php
                                $attendance = $memorizer->attendances->first();
                                $status = AttendanceStatus::resolve($attendance);
                                $score = $attendance?->score;

                                $statusClass = $status === AttendanceStatus::PRESENT ? 'st-present' : 'st-absent';
                                $statusMark = $status === AttendanceStatus::PRESENT ? '✓' : '◦';
                                $statusMarkClass = $status === AttendanceStatus::PRESENT ? 'present' : 'absent';

                                $scoreClass = match ($score) {
                                    MemorizationScore::EXCELLENT => 'sc-excellent',
                                    MemorizationScore::GOOD, MemorizationScore::VERY_GOOD => 'sc-good',
                                    MemorizationScore::FAIR, MemorizationScore::AVERAGE, MemorizationScore::ACCEPTABLE => 'sc-fair',
                                    MemorizationScore::POOR, MemorizationScore::NOT_MEMORIZED => 'sc-poor',
                                    default => 'sc-dash',
                                };
                            @endphp
                            <tr>
                                <td class="idx">{{ $pageIndex * 25 + $loop->iteration }}</td>
                                <td class="name">{{ $memorizer->name }}</td>
                                <td class="{{ $statusClass }}">
                                    <span class="st-mark {{ $statusMarkClass }}">{{ $statusMark }}</span>{{ $status->getShortLabel() }}
                                </td>
                                <td class="{{ $scoreClass }}">
                                    @if ($score === MemorizationScore::EXCELLENT)
                                        <span class="sc-star">★</span>
                                    @endif
                                    {{ $score?->getLabel() ?? '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <div class="footer-signoff">
                    ◆ ◆ ◆
                    <span class="stamp">تم التصدير في {{ $exportStamp }}</span>
                </div>
            </div>
        </div>
    @endforeach
</div>
