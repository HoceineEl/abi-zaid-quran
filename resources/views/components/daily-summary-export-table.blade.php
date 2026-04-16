<div style="direction: rtl; font-family: 'Almarai', sans-serif;">
    <style>
        :root {
            --bg-primary: #ffffff;
            --bg-secondary: #f8fafc;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --border-color: #e2e8f0;
        }

        [data-theme="dark"] {
            --bg-primary: #1e293b;
            --bg-secondary: #0f172a;
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --border-color: #334155;
        }

        .ds-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.82rem;
            border-radius: 8px;
            overflow: hidden;
        }

        .ds-table thead tr th {
            padding: 11px 10px;
            text-align: center;
            font-weight: 700;
            font-size: 0.78rem;
            color: #ffffff;
            letter-spacing: 0.02em;
            white-space: nowrap;
        }

        .ds-table tbody td {
            padding: 8px 10px;
            border-bottom: 1px solid var(--border-color);
            text-align: center;
            vertical-align: middle;
            color: var(--text-primary);
        }

        .ds-table tbody tr:nth-child(even) td {
            background-color: var(--bg-secondary);
        }

        .ds-table tbody tr:nth-child(odd) td {
            background-color: var(--bg-primary);
        }

        .th-name      { background-color: #1e3a5f; }
        .th-total     { background-color: #1d4ed8; }
        .th-present   { background-color: #15803d; }
        .th-absent    { background-color: #b91c1c; }
        .th-justified { background-color: #b45309; }
        .th-undefined { background-color: #475569; }

        .td-name {
            text-align: right !important;
            font-weight: 600;
            color: var(--text-primary);
            padding-right: 14px !important;
            min-width: 160px;
        }

        .td-total {
            font-weight: 700;
            color: #1d4ed8;
            font-size: 0.9rem;
        }

        .td-present   { font-weight: 600; color: #15803d; }
        .td-absent    { font-weight: 600; color: #b91c1c; }
        .td-justified { font-weight: 600; color: #92400e; }
        .td-undefined { font-weight: 500; color: #475569; }

        .pct {
            font-size: 0.68rem;
            font-weight: 400;
            opacity: 0.70;
            margin-right: 2px;
        }

        [data-theme="dark"] .td-total     { color: #60a5fa; }
        [data-theme="dark"] .td-present   { color: #4ade80; }
        [data-theme="dark"] .td-absent    { color: #f87171; }
        [data-theme="dark"] .td-justified { color: #fbbf24; }
        [data-theme="dark"] .td-undefined { color: #94a3b8; }
    </style>

    @php
        $chunks = $groups->chunk(45);

        $statColumns = [
            ['key' => 'present',            'class' => 'present',   'label' => 'حاضر'],
            ['key' => 'absent',             'class' => 'absent',    'label' => 'غائب'],
            ['key' => 'absent_with_reason', 'class' => 'justified', 'label' => 'غائب بعذر'],
            ['key' => 'not_specified',      'class' => 'undefined', 'label' => 'لم يحدد'],
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
                            <td class="td-total">{{ $total }}</td>

                            @foreach ($statColumns as $column)
                                @php $value = $group[$column['key']]; @endphp
                                <td class="td-{{ $column['class'] }}">
                                    @if ($value > 0)
                                        {{ $value }}<span class="pct">{{ round($value / $total * 100) }}%</span>
                                    @else
                                        -
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
