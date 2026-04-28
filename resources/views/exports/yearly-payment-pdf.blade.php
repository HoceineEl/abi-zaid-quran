@php
    use Carbon\Carbon;

    $fmtAmount = fn (float $v) => rtrim(rtrim(number_format($v, 2, '.', ','), '0'), '.');
    $rangeLabel = $startDate->format('Y-m-d') . ' — ' . $endDate->format('Y-m-d');
    $generatedAt = now()->format('Y-m-d · H:i');

    $totalRows = count($rows);
    $exemptCount = collect($rows)->where('exempt', true)->count();
    $activeCount = $totalRows - $exemptCount;
    $monthCount = count($months);
    $maxPossible = $activeCount * $monthCount;
    $totalPaidCells = collect($rows)->where('exempt', false)->sum('paid_count');
    $coverage = $maxPossible > 0 ? round($totalPaidCells / $maxPossible * 100) : 0;
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<title>متابعة أداء الواجب</title>
<style>
    @page {
        margin: 6mm 5mm 7mm 5mm;
    }
    * { box-sizing: border-box; }
    body {
        font-family: 'cairo', sans-serif;
        color: #2d1f0f;
        font-size: 7.5pt;
        margin: 0;
        padding: 0;
    }

    .header {
        background: #0d5d3f;
        color: #faf4e3;
        padding: 4px 10px;
        border-bottom: 2px solid #b8860b;
        margin-bottom: 4px;
    }
    .header .row {
        display: table;
        width: 100%;
    }
    .header .title {
        display: table-cell;
        font-size: 12pt;
        font-weight: 700;
        margin: 0;
        text-align: right;
    }
    .header .meta {
        display: table-cell;
        font-size: 7.5pt;
        color: #e7d9a8;
        text-align: left;
        vertical-align: middle;
    }
    .header .meta b { color: #faf4e3; }

    .summary {
        margin: 0 0 4px;
        font-size: 7pt;
        text-align: center;
    }
    .summary .pill {
        display: inline-block;
        padding: 1px 7px;
        margin: 0 2px;
        border-radius: 8px;
        background: #faf4e3;
        border: 1px solid #d4c088;
        color: #8b6914;
        font-weight: 700;
    }
    .summary .pill.emerald { background: #0d5d3f; color: #faf4e3; border-color: #b8860b; }

    table.report {
        width: 100%;
        border-collapse: collapse;
        font-size: 7.5pt;
    }
    table.report thead th {
        background: #0d5d3f;
        color: #faf4e3;
        border: 1px solid #b8860b;
        padding: 3px 2px;
        font-weight: 700;
        font-size: 7.5pt;
        line-height: 1.15;
    }
    table.report tbody td {
        border: 1px solid #e7d9a8;
        padding: 1.5px 2px;
        text-align: center;
        vertical-align: middle;
        background: #ffffff;
        line-height: 1.2;
    }
    table.report tbody tr:nth-child(even) td {
        background: #fdf8e8;
    }
    table.report tbody td.idx {
        color: #8b6914;
        font-weight: 700;
        width: 18px;
    }
    table.report tbody td.name {
        text-align: right;
        font-weight: 700;
        padding-right: 5px;
        white-space: nowrap;
        max-width: 110px;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    table.report tbody td.phone,
    table.report tbody td.group {
        font-size: 7pt;
        color: #2d1f0f;
        white-space: nowrap;
    }

    td.cell-paid {
        background: #c6efce !important;
        color: #006100;
        font-weight: 700;
    }
    td.cell-unpaid {
        background: #ffc7ce !important;
        color: #9c0006;
        font-weight: 700;
    }
    td.cell-exempt {
        background: #ddebf7 !important;
        color: #0066cc;
        font-weight: 700;
    }
    td.cell-total {
        background: #fff2cc !important;
        color: #8b6914;
        font-weight: 700;
    }
    td.cell-summary {
        font-size: 7pt;
        font-weight: 700;
    }
    td.cell-summary.full { background: #c6efce !important; color: #006100; }
    td.cell-summary.partial { background: #fff2cc !important; color: #8b6914; }
    td.cell-summary.empty { background: #ffc7ce !important; color: #9c0006; }
    td.cell-summary.exempt { background: #ddebf7 !important; color: #0066cc; }

    table.report tfoot td {
        background: #0d5d3f;
        color: #faf4e3;
        font-weight: 700;
        border: 1px solid #b8860b;
        padding: 4px 2px;
        text-align: center;
        font-size: 8pt;
    }
    table.report tfoot td.label {
        text-align: center;
        font-size: 8.5pt;
    }
    table.report tfoot td.grand {
        background: #b8860b;
        color: #ffffff;
    }

    table.report thead {
        display: table-header-group;
    }
    table.report tfoot {
        display: table-row-group;
    }
    table.report tr {
        page-break-inside: avoid;
    }

    .footer {
        margin-top: 3px;
        text-align: center;
        font-size: 6.5pt;
        color: #8b6914;
    }
</style>
</head>
<body>
    <div class="header">
        <div class="row">
            <div class="title">متابعة أداء الواجب</div>
            <div class="meta">
                <b>الفترة:</b> {{ $startDate->format('Y-m-d') }} → {{ $endDate->format('Y-m-d') }}
                &nbsp;·&nbsp; <b>التقرير:</b> {{ now()->format('Y-m-d') }}
            </div>
        </div>
    </div>

    <div class="summary">
        <span class="pill emerald">{{ $totalRows }} طالب</span>
        <span class="pill">نشطون: {{ $activeCount }}</span>
        <span class="pill">معفيون: {{ $exemptCount }}</span>
        <span class="pill">عدد الأشهر: {{ $monthCount }}</span>
        <span class="pill">نسبة الأداء: {{ $coverage }}%</span>
        <span class="pill emerald">الإجمالي: {{ $fmtAmount($totals['grand_total']) }} د.م</span>
    </div>

    <table class="report">
        <thead>
            <tr>
                <th style="width:22px">#</th>
                <th style="width:130px">الاسم</th>
                <th style="width:70px">الهاتف</th>
                <th style="width:70px">المجموعة</th>
                @foreach ($months as $month)
                    <th>{{ $month['label'] }}</th>
                @endforeach
                <th>المجموع<br>(د.م)</th>
                <th>الملخص</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td class="idx">{{ $row['index'] }}</td>
                    <td class="name">{{ $row['name'] }}</td>
                    <td class="phone">{{ $row['phone'] }}</td>
                    <td class="group">{{ $row['group'] }}</td>
                    @foreach ($months as $month)
                        @php $cell = $row['cells'][$month['key']]; @endphp
                        @switch($cell['type'])
                            @case('paid')
                                <td class="cell-paid">{{ $fmtAmount($cell['amount']) }}</td>
                                @break
                            @case('exempt')
                                <td class="cell-exempt">معفي</td>
                                @break
                            @default
                                <td class="cell-unpaid">—</td>
                        @endswitch
                    @endforeach
                    @if ($row['exempt'])
                        <td class="cell-exempt">معفي</td>
                        <td class="cell-summary exempt">معفي</td>
                    @else
                        <td class="cell-total">{{ $fmtAmount($row['row_total']) }}</td>
                        @php
                            $summaryClass = match (true) {
                                $row['paid_count'] >= $row['months_count'] => 'full',
                                $row['paid_count'] === 0 => 'empty',
                                default => 'partial',
                            };
                        @endphp
                        <td class="cell-summary {{ $summaryClass }}">
                            {{ $row['paid_count'] }} / {{ $row['months_count'] }}
                        </td>
                    @endif
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td class="label" colspan="4">الإجمالي (د.م)</td>
                @foreach ($months as $month)
                    <td>{{ $fmtAmount($totals['by_month'][$month['key']]) }}</td>
                @endforeach
                <td class="grand">{{ $fmtAmount($totals['grand_total']) }}</td>
                <td>—</td>
            </tr>
        </tfoot>
    </table>

    <div class="footer">
        تم إنشاء التقرير في {{ $generatedAt }}
    </div>
</body>
</html>
