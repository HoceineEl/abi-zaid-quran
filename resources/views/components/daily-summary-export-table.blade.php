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
            border-collapse: separate;
            border-spacing: 0;
            font-size: 0.9rem;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
        }

        .ds-table thead tr th {
            padding: 16px 10px;
            text-align: center;
            font-weight: 800;
            font-size: 0.95rem;
            color: #ffffff;
            white-space: nowrap;
            line-height: 1.3;
            letter-spacing: 0;
        }

        .ds-table tbody td {
            padding: 12px 10px;
            border-bottom: 1px solid var(--border-color);
            text-align: center;
            vertical-align: middle;
            color: var(--text-primary);
        }

        .ds-table tbody tr:last-child td { border-bottom: none; }

        .ds-table tbody tr:nth-child(even) td { background-color: var(--bg-secondary); }
        .ds-table tbody tr:nth-child(odd)  td { background-color: var(--bg-primary);   }

        .th-name      { background-color: #1e3a5f; min-width: 200px; text-align: right !important; padding-right: 16px !important; }
        .th-total     { background-color: #1d4ed8; min-width: 90px; }
        .th-present   { background-color: #15803d; min-width: 90px; }
        .th-absent    { background-color: #b91c1c; min-width: 90px; }
        .th-justified { background-color: #b45309; min-width: 100px; }
        .th-undefined { background-color: #475569; min-width: 90px; }

        .td-name {
            text-align: right !important;
            font-weight: 700;
            font-size: 0.95rem;
            color: var(--text-primary);
            padding-right: 16px !important;
        }

        /* Each stat cell: big number on top, clearly separated percentage pill below */
        .cell-block {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 5px;
            line-height: 1;
            direction: ltr;
        }

        .cell-num {
            font-weight: 800;
            font-size: 1.3rem;
            line-height: 1;
            letter-spacing: -0.5px;
        }

        .cell-pct {
            font-size: 0.7rem;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 999px;
            line-height: 1.3;
            letter-spacing: 0.3px;
            display: inline-block;
        }

        .cell-dash {
            opacity: 0.3;
            font-size: 1.2rem;
            font-weight: 600;
        }

        .td-total     .cell-num { color: #1d4ed8; }
        .td-present   .cell-num { color: #15803d; }
        .td-absent    .cell-num { color: #b91c1c; }
        .td-justified .cell-num { color: #92400e; }
        .td-undefined .cell-num { color: #475569; }

        .td-present   .cell-pct { background: #dcfce7; color: #15803d; }
        .td-absent    .cell-pct { background: #fee2e2; color: #b91c1c; }
        .td-justified .cell-pct { background: #fef3c7; color: #92400e; }
        .td-undefined .cell-pct { background: #f1f5f9; color: #475569; }

        [data-theme="dark"] .td-total     .cell-num { color: #60a5fa; }
        [data-theme="dark"] .td-present   .cell-num { color: #4ade80; }
        [data-theme="dark"] .td-absent    .cell-num { color: #f87171; }
        [data-theme="dark"] .td-justified .cell-num { color: #fbbf24; }
        [data-theme="dark"] .td-undefined .cell-num { color: #94a3b8; }

        [data-theme="dark"] .td-present   .cell-pct { background: rgba(21,128,61,0.25);  color: #4ade80; }
        [data-theme="dark"] .td-absent    .cell-pct { background: rgba(185,28,28,0.25);  color: #f87171; }
        [data-theme="dark"] .td-justified .cell-pct { background: rgba(180,83,9,0.25);   color: #fbbf24; }
        [data-theme="dark"] .td-undefined .cell-pct { background: rgba(71,85,105,0.35);  color: #94a3b8; }

        .table-page + .table-page { margin-top: 20px; }
    </style>

    @php
        $chunks = $groups->chunk(18);

        $statColumns = [
            ['key' => 'present',            'class' => 'present',   'label' => "حاضر"],
            ['key' => 'absent',             'class' => 'absent',    'label' => "غائب"],
            ['key' => 'absent_with_reason', 'class' => 'justified', 'label' => "غائب بعذر"],
            ['key' => 'not_specified',      'class' => 'undefined', 'label' => "لم يحدد"],
        ];
    @endphp

    @foreach ($chunks as $pageIndex => $chunk)
        <div class="table-page" data-page="{{ $pageIndex + 1 }}">
            <table class="ds-table">
                <thead>
                    <tr>
                        <th class="th-name">اسم المجموعة</th>
                        <th class="th-total">إجمالي الطلاب</th>
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
