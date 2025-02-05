<div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow" style="direction: rtl;">
    <div class="text-center mb-4">
        <h2 class="text-xl font-bold text-gray-900 dark:text-white">{{ $group->name }}</h2>
        <p class="text-gray-600 dark:text-gray-400">تقرير الحضور والتقييم ليوم
            {{ \Carbon\Carbon::parse($date)->format('Y/m/d') }}</p>
    </div>

    <style>
        :root {
            --border-color: #e5e7eb;
            --bg-header: #f3f4f6;
            --bg-secondary: #f9fafb;
            --text-primary: #111827;
            --text-secondary: #4b5563;
            --success-color: #059669;
            --danger-color: #dc2626;
            --warning-color: #d97706;
        }

        :root[data-theme="dark"] {
            --border-color: #374151;
            --bg-header: #1f2937;
            --bg-secondary: #111827;
            --text-primary: #f9fafb;
            --text-secondary: #9ca3af;
            --success-color: #34d399;
            --danger-color: #f87171;
            --warning-color: #fbbf24;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
            font-family: 'Almarai', sans-serif;
            color: var(--text-primary);
        }

        th,
        td {
            border: 1px solid var(--border-color);
            padding: 12px;
            text-align: center;
        }

        th {
            background-color: var(--bg-header);
            font-weight: 600;
        }

        tr:nth-child(even) {
            background-color: var(--bg-secondary);
        }

        .status-present {
            color: var(--success-color);
        }

        .status-absent {
            color: var(--danger-color);
        }
    </style>

    <table>
        <thead>
            <tr>
                <th class="dark:text-gray-200">الإسم</th>
                <th class="dark:text-gray-200">الحضور</th>
                <th class="dark:text-gray-200">التقييم</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($memorizers as $index => $memorizer)
                @php
                    $attendance = $memorizer->attendances->first();
                    $status = $attendance ? ($attendance->check_in_time ? 'حاضر' : 'غائب') : 'غائب';
                    $statusClass = $status === 'حاضر' ? 'status-present' : 'status-absent';

                    $scoreColor = match ($attendance?->score ?? null) {
                        \App\Enums\MemorizationScore::EXCELLENT => 'text-emerald-600 dark:text-emerald-400',
                        \App\Enums\MemorizationScore::VERY_GOOD => 'text-green-600 dark:text-green-400',
                        \App\Enums\MemorizationScore::GOOD => 'text-blue-600 dark:text-blue-400',
                        \App\Enums\MemorizationScore::FAIR => 'text-amber-600 dark:text-amber-400',
                        \App\Enums\MemorizationScore::POOR => 'text-red-600 dark:text-red-400',
                        \App\Enums\MemorizationScore::NOT_MEMORIZED => 'text-rose-600 dark:text-rose-400',
                        default => 'text-gray-600 dark:text-gray-400',
                    };
                @endphp
                <tr>
                    <td class="text-right dark:text-gray-200">{{ $memorizer->name }}</td>
                    <td class="{{ $statusClass }}">{{ $status }}</td>
                    <td class="{{ $scoreColor }}">
                        {{ $attendance?->score?->getLabel() ?? '—' }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="mt-4 text-sm text-gray-600 dark:text-gray-400 text-left">
        تم إنشاء هذا التقرير في {{ now()->format('H:i') }}
    </div>
</div>
