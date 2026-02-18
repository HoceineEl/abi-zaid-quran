<div style="direction: rtl; font-family: 'Almarai', sans-serif;">
    <style>
        :root {
            --bg-primary: #ffffff;
            --bg-secondary: #f9fafb;
            --bg-header: #f3f4f6;
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --border-color: #e5e7eb;
            --success-color: #059669;
            --danger-color: #dc2626;
            --emerald: #059669;
            --green: #16a34a;
            --blue: #2563eb;
            --amber: #d97706;
            --red: #dc2626;
            --rose: #e11d48;
            --gray: #6b7280;
        }

        [data-theme="dark"] {
            --bg-primary: #1f2937;
            --bg-secondary: #374151;
            --bg-header: #111827;
            --text-primary: #f3f4f6;
            --text-secondary: #9ca3af;
            --border-color: #4b5563;
            --success-color: #34d399;
            --danger-color: #f87171;
            --emerald: #34d399;
            --green: #4ade80;
            --blue: #60a5fa;
            --amber: #fbbf24;
            --red: #f87171;
            --rose: #fb7185;
            --gray: #9ca3af;
        }

        .table-page table {
            width: 100%;
            border-collapse: collapse;
            color: var(--text-primary);
            border: 1px solid var(--border-color);
            background-color: var(--bg-primary);
        }

        .table-page th,
        .table-page td {
            border: 1px solid var(--border-color);
            padding: 0.75rem 1rem;
            text-align: center;
            vertical-align: middle;
        }

        .table-page th {
            background-color: var(--bg-header);
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.9rem;
        }

        .table-page tr:nth-child(even) {
            background-color: var(--bg-secondary);
        }

        .table-page td.text-right {
            text-align: right;
            color: var(--text-primary);
            font-weight: 500;
        }

        .status-present { color: var(--success-color); }
        .status-absent  { color: var(--danger-color); }

        .text-emerald { color: var(--emerald); }
        .text-green   { color: var(--green); }
        .text-blue    { color: var(--blue); }
        .text-amber   { color: var(--amber); }
        .text-red     { color: var(--red); }
        .text-rose    { color: var(--rose); }
        .text-gray    { color: var(--gray); }
    </style>

    @php
        $chunks = $memorizers->chunk(25);
    @endphp

    @foreach ($chunks as $pageIndex => $chunk)
        <div class="table-page" data-page="{{ $pageIndex + 1 }}">
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
                    @foreach ($chunk as $memorizer)
                        @php
                            $attendance = $memorizer->attendances->first();
                            $status     = $attendance ? ($attendance->check_in_time ? 'حاضر' : 'غائب') : 'غائب';
                            $statusClass = $status === 'حاضر' ? 'status-present' : 'status-absent';

                            $scoreColor = match ($attendance?->score ?? null) {
                                \App\Enums\MemorizationScore::EXCELLENT      => 'text-emerald',
                                \App\Enums\MemorizationScore::VERY_GOOD      => 'text-green',
                                \App\Enums\MemorizationScore::GOOD           => 'text-blue',
                                \App\Enums\MemorizationScore::FAIR           => 'text-amber',
                                \App\Enums\MemorizationScore::ACCEPTABLE     => 'text-gray',
                                \App\Enums\MemorizationScore::POOR           => 'text-red',
                                \App\Enums\MemorizationScore::NOT_MEMORIZED  => 'text-rose',
                                default => 'text-gray',
                            };
                        @endphp
                        <tr>
                            <td>{{ $pageIndex * 25 + $loop->iteration }}</td>
                            <td class="text-right">{{ $memorizer->name }}</td>
                            <td class="{{ $statusClass }}">{{ $status }}</td>
                            <td class="{{ $scoreColor }}">
                                {{ $attendance?->score?->getLabel() ?? '—' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach
</div>
