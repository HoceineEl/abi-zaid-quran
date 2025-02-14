<div class="attendance-export-container" style="direction: rtl; font-family: 'Almarai', sans-serif;">
    <div class="header">
        <h2>{{ $group->name }}</h2>
        <p>تقرير الحضور والتقييم ليوم {{ \Carbon\Carbon::parse($date)->format('Y/m/d') }}</p>
    </div>

    <style>
        /* Light mode styles */
        :root {
            --bg-primary: #ffffff;
            --bg-secondary: #f9fafb;
            --bg-header: #f3f4f6;
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --border-color: #e5e7eb;
            --success-color: #059669;
            --warning-color: #d97706;
            --danger-color: #dc2626;
            --emerald: #059669;
            --green: #16a34a;
            --blue: #2563eb;
            --amber: #d97706;
            --red: #dc2626;
            --rose: #e11d48;
            --gray: #6b7280;
        }

        /* Dark mode styles */
        [data-theme="dark"] {
            --bg-primary: #1f2937;
            --bg-secondary: #374151;
            --bg-header: #111827;
            --text-primary: #f3f4f6;
            --text-secondary: #9ca3af;
            --border-color: #4b5563;
            --success-color: #34d399;
            --warning-color: #fbbf24;
            --danger-color: #f87171;
            --emerald: #34d399;
            --green: #4ade80;
            --blue: #60a5fa;
            --amber: #fbbf24;
            --red: #f87171;
            --rose: #fb7185;
            --gray: #9ca3af;
        }

        .attendance-export-container {
            background-color: var(--bg-primary);
            padding: 2rem;
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .header h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0 0 0.75rem 0;
        }

        .header p {
            color: var(--text-secondary);
            margin: 0;
            font-size: 1rem;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1.5rem;
            color: var(--text-primary);
            border: 1px solid var(--border-color);
            background-color: var(--bg-primary);
        }

        th,
        td {
            border: 1px solid var(--border-color);
            padding: 1rem;
            text-align: center;
            vertical-align: middle;
        }

        th {
            background-color: var(--bg-header);
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.95rem;
            white-space: nowrap;
        }

        tr:nth-child(even) {
            background-color: var(--bg-secondary);
        }

        tr:hover {
            background-color: var(--bg-header);
        }

        .status-present {
            color: var(--success-color);
        }

        .status-absent {
            color: var(--danger-color);
        }

        td.text-right {
            text-align: right;
            color: var(--text-primary);
            font-weight: 500;
        }

        .text-emerald {
            color: var(--emerald);
        }

        .text-green {
            color: var(--green);
        }

        .text-blue {
            color: var(--blue);
        }

        .text-amber {
            color: var(--amber);
        }

        .text-red {
            color: var(--red);
        }

        .text-rose {
            color: var(--rose);
        }

        .text-gray {
            color: var(--gray);
        }

        .report-footer {
            margin-top: 1.5rem;
            text-align: left;
            font-size: 0.875rem;
            color: var(--text-secondary);
            padding: 0 0.5rem;
        }
    </style>

    <table>
        <thead>
            <tr>
                <th>الإسم</th>
                <th>الحضور</th>
                <th>التقييم</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($memorizers as $index => $memorizer)
                @php
                    $attendance = $memorizer->attendances->first();
                    $status = $attendance ? ($attendance->check_in_time ? 'حاضر' : 'غائب') : 'غائب';
                    $statusClass = $status === 'حاضر' ? 'status-present' : 'status-absent';

                    $scoreColor = match ($attendance?->score ?? null) {
                        \App\Enums\MemorizationScore::EXCELLENT => 'text-emerald',
                        \App\Enums\MemorizationScore::VERY_GOOD => 'text-green',
                        \App\Enums\MemorizationScore::GOOD => 'text-blue',
                        \App\Enums\MemorizationScore::FAIR => 'text-amber',
                        \App\Enums\MemorizationScore::ACCEPTABLE => 'text-gray',
                        \App\Enums\MemorizationScore::POOR => 'text-red',
                        \App\Enums\MemorizationScore::NOT_MEMORIZED => 'text-rose',
                        default => 'text-gray',
                    };
                @endphp
                <tr>
                    <td class="text-right">{{ $memorizer->name }}</td>
                    <td class="{{ $statusClass }}">{{ $status }}</td>
                    <td class="{{ $scoreColor }}">
                        {{ $attendance?->score?->getLabel() ?? '—' }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="report-footer">
        تم إنشاء هذا التقرير في {{ now()->format('H:i') }}
    </div>
</div>
