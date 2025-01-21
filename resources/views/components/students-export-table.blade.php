<div class="export-table-container" style="direction: rtl; font-family: 'Almarai', sans-serif;">
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
        }

        .export-table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
            background-color: var(--bg-primary);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }

        .export-table th,
        .export-table td {
            border: 1px solid var(--border-color);
            padding: 16px;
            text-align: center;
            vertical-align: middle;
        }

        .export-table th {
            background-color: var(--bg-header);
            font-weight: 600;
            font-size: 0.95rem;
            white-space: nowrap;
            color: var(--text-primary);
        }

        .export-table tr:nth-child(even) {
            background-color: var(--bg-secondary);
        }

        .export-table tr:hover {
            background-color: var(--bg-header);
        }

        .status-icon {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            justify-content: center;
        }

        .status-icon svg {
            width: 24px;
            height: 24px;
            stroke-width: 2;
            stroke: currentColor;
        }

        .status-memorized {
            color: var(--success-color);
        }

        .status-absent {
            color: var(--danger-color);
        }

        .status-pending {
            color: var(--warning-color);
        }

        .consecutive-absent {
            color: var(--danger-color);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .consecutive-absent svg {
            width: 24px;
            height: 24px;
            stroke-width: 2;
        }

        .student-name {
            font-weight: 600;
            font-size: 1.2rem;
        }

        .student-name.memorized {
            color: var(--success-color);
        }

        .student-name.absent {
            color: var(--danger-color);
        }

        .student-name.pending {
            color: var(--warning-color);
        }

        .phone-number {
            color: var(--text-secondary);
            font-size: 1.1rem;
            direction: ltr;
        }

        .index-column {
            width: 40px;
            text-align: center;
            color: var(--text-secondary);
        }

        .city {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
    </style>

    @php
        $chunks = $students->chunk(50);
        $totalPages = $chunks->count();
    @endphp

    @foreach ($chunks as $pageIndex => $studentsChunk)
        <div class="table-page" data-page="{{ $pageIndex + 1 }}">
            <table class="export-table">
                <thead>
                    <tr>
                        <th class="index-column">#</th>
                        <th>الاسم</th>
                        <th>رقم الهاتف</th>
                        <th>المدينة</th>
                        <th>الحالة اليوم</th>
                        <th>ملاحظات</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($studentsChunk as $index => $student)
                        @php
                            $todayProgress = $student->progresses->where('date', now()->format('Y-m-d'))->first();
                            $consecutiveAbsentDays = $student->consecutiveAbsentDays;
                            $status = $todayProgress?->status ?? 'pending';
                        @endphp
                        <tr>
                            <td class="index-column">{{ $pageIndex * 25 + $index + 1 }}</td>
                            <td>
                                <span class="student-name {{ $status }}">{{ $student->name }}</span>
                            </td>
                            <td>
                                <span class="phone-number {{ $consecutiveAbsentDays > 0 ? 'consecutive-absent' : '' }}">
                                    {{ $student->phone }}
                                </span>
                            </td>
                            <td>
                                <span class="city">{{ $student->city }}</span>
                            </td>
                            <td>
                                <span class="status-{{ $status }} status-icon">
                                    @if (!$todayProgress)
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                            stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                        </svg>
                                    @else
                                        @switch($todayProgress->status)
                                            @case('memorized')
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                    stroke-width="2" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                                </svg>
                                            @break

                                            @case('absent')
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                    stroke-width="2" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                            @break

                                            @default
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                    stroke-width="2" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                                </svg>
                                        @endswitch
                                    @endif
                                </span>
                            </td>
                            <td>
                                @if ($student->needsCall())
                                    <span class="consecutive-absent">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                            stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M14.25 9.75v-4.5m0 4.5h4.5m-4.5 0l6-6m-3 18c-8.284 0-15-6.716-15-15V4.5A2.25 2.25 0 014.5 2.25h1.372c.516 0 .966.351 1.091.852l1.106 4.423c.11.44-.054.902-.417 1.173l-1.293.97a1.062 1.062 0 00-.38 1.21 12.035 12.035 0 007.143 7.143c.441.162.928-.004 1.21-.38l.97-1.293c.271-.363.734-.527 1.173-.417l4.423 1.106c.5.125.852.575.852 1.091V19.5a2.25 2.25 0 01-2.25 2.25h-2.25z" />
                                        </svg>
                                        <small>(يحتاج اتصال)</small>
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @if ($totalPages > 1)
                <div class="page-indicator" style="text-align: center; margin-top: 10px; color: var(--text-secondary);">
                    صفحة {{ $pageIndex + 1 }} من {{ $totalPages }}
                </div>
            @endif
        </div>
    @endforeach
</div>
