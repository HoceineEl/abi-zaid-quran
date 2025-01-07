<div class="bg-white p-4 rounded-lg shadow" style="direction: rtl;">
    <div class="text-center mb-4">
        <h2 class="text-xl font-bold">{{ $group->name }}</h2>
        <p class="text-gray-600">تقرير الحضور والتقييم ليوم {{ \Carbon\Carbon::parse($date)->format('Y/m/d') }}</p>
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

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
            font-family: 'Almarai', sans-serif;
        }

        th, td {
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
                <th>#</th>
                <th>الإسم</th>
                <th>الحضور</th>
                <th>التقييم</th>
            </tr>
        </thead>
        <tbody>
            @foreach($memorizers as $index => $memorizer)
                @php
                    $attendance = $memorizer->attendances->first();
                    $status = $attendance ? ($attendance->check_in_time ? 'حاضر' : 'غائب') : 'غائب';
                    $statusClass = $status === 'حاضر' ? 'status-present' : 'status-absent';
                    
                    $scoreColor = match($attendance?->score ?? '') {
                        'ممتاز' => 'text-emerald-600',
                        'حسن' => 'text-green-600',
                        'جيد' => 'text-blue-600',
                        'لا بأس به' => 'text-amber-600',
                        'لم يحفظ' => 'text-red-600',
                        'لم يستظهر' => 'text-rose-600',
                        default => 'text-gray-600'
                    };

                    $notes = $attendance?->notes ? implode('، ', array_map(fn($note) => \App\Enums\Troubles::from($note)->getLabel(), $attendance->notes)) : '';
                @endphp
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td class="text-right">{{ $memorizer->name }}</td>
                    <td class="{{ $statusClass }}">{{ $status }}</td>
                    <td class="{{ $scoreColor }}">
                        {{ $attendance?->score ?? '—' }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="mt-4 text-sm text-gray-600 text-left">
        تم إنشاء هذا التقرير في {{ now()->format('H:i') }}
    </div>
</div> 