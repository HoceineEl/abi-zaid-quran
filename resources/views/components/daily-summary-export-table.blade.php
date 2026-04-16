<div style="direction: rtl; font-family: 'Almarai', sans-serif;">
    <style>
        :root {
            --bg-primary: #ffffff;
            --bg-secondary: #f8fafc;
            --text-primary: #1e293b;
            --border-color: #e2e8f0;
        }

        [data-theme="dark"] {
            --bg-primary: #1e293b;
            --bg-secondary: #0f172a;
            --text-primary: #f1f5f9;
            --border-color: #334155;
        }

        .ds-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }

        .ds-table thead tr th {
            padding: 12px 8px;
            text-align: center;
            font-weight: 700;
            font-size: 0.8rem;
            color: #ffffff;
            white-space: pre-line;
            line-height: 1.35;
        }

        .ds-table tbody td {
            padding: 7px 8px;
            border-bottom: 1px solid var(--border-color);
            text-align: center;
            vertical-align: middle;
            color: var(--text-primary);
        }

        .ds-table tbody tr:nth-child(even) td { background-color: var(--bg-secondary); }
        .ds-table tbody tr:nth-child(odd)  td { background-color: var(--bg-primary);   }

        .th-name      { background-color: #1e3a5f; min-width: 170px; text-align: right !important; padding-right: 14px !important; }
        .th-total     { background-color: #1d4ed8; min-width: 72px; }
        .th-present   { background-color: #15803d; min-width: 72px; }
        .th-absent    { background-color: #b91c1c; min-width: 72px; }
        .th-justified { background-color: #b45309; min-width: 80px; }
        .th-undefined { background-color: #475569; min-width: 72px; }

        .td-name {
            text-align: right !important;
            font-weight: 600;
            color: var(--text-primary);
            padding-right: 14px !important;
        }

        /* Each stat cell shows number + pct stacked */
        .cell-block {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0;
            line-height: 1;
        }

        .cell-num {
            font-weight: 700;
            font-size: 1rem;
            line-height: 1.2;
        }

        .cell-pct {
            font-size: 0.65rem;
            font-weight: 500;
            opacity: 0.58;
            line-height: 1.2;
        }

        .cell-dash { opacity: 0.4; font-size: 0.9rem; }

        .td-total     .cell-num { color: #1d4ed8; }
        .td-present   .cell-num { color: #15803d; }
        .td-absent    .cell-num { color: #b91c1c; }
        .td-justified .cell-num { color: #92400e; }
        .td-undefined .cell-num { color: #475569; }

        .td-total     .cell-pct { color: #1d4ed8; }
        .td-present   .cell-pct { color: #15803d; }
        .td-absent    .cell-pct { color: #b91c1c; }
        .td-justified .cell-pct { color: #92400e; }
        .td-undefined .cell-pct { color: #475569; }

        [data-theme="dark"] .td-total     .cell-num { color: #60a5fa; }
        [data-theme="dark"] .td-present   .cell-num { color: #4ade80; }
        [data-theme="dark"] .td-absent    .cell-num { color: #f87171; }
        [data-theme="dark"] .td-justified .cell-num { color: #fbbf24; }
        [data-theme="dark"] .td-undefined .cell-num { color: #94a3b8; }

        [data-theme="dark"] .td-total     .cell-pct { color: #60a5fa; }
        [data-theme="dark"] .td-present   .cell-pct { color: #4ade80; }
        [data-theme="dark"] .td-absent    .cell-pct { color: #f87171; }
        [data-theme="dark"] .td-justified .cell-pct { color: #fbbf24; }
        [data-theme="dark"] .td-undefined .cell-pct { color: #94a3b8; }
    </style>

    @php
        $chunks = $groups->chunk(28);

        $statColumns = [
            ['key' => 'present',            'class' => 'present',   'label' => "حاضر"],
            ['key' => 'absent',             'class' => 'absent',    'label' => "غائب"],
            ['key' => 'absent_with_reason', 'class' => 'justified', 'label' => "غائب\nبعذر"],
            ['key' => 'not_specified',      'class' => 'undefined', 'label' => "لم\nيحدد"],
        ];
    @endphp

    @foreach ($chunks as $pageIndex => $chunk)
        <div class="table-page" data-page="{{ $pageIndex + 1 }}">
            <table class="ds-table">
                <thead>
                    <tr>
                        <th class="th-name">اسم المجموعة</th>
                        <th class="th-total">إجمالي&#10;الطلاب</th>
                        @foreach ($statColumns as $column)
                            <th class="th-{{ $column['class'] }}">{{ $column['label'] }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($chunk as $group)
                        @php $total = $group['total_students']; @endphp
                        <tr>
                            <td class="td-name">{{ $group['name'] }}</td>

                            <td class="td-total">
                                <div class="cell-block">
                                    <span class="cell-num">{{ $total }}</span>
                                </div>
                            </td>

                            @foreach ($statColumns as $column)
                                @php $value = $group[$column['key']]; @endphp
                                <td class="td-{{ $column['class'] }}">
                                    @if ($value > 0)
                                        <div class="cell-block">
                                            <span class="cell-num">{{ $value }}</span>
                                            <span class="cell-pct">{{ round($value / $total * 100) }}%</span>
                                        </div>
                                    @else
                                        <span class="cell-dash">—</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach
</div>
