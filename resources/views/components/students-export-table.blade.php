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
            font-weight: 700;
            font-size: 1.1rem;
            white-space: nowrap;
            color: var(--text-primary);
            padding: 20px 16px;
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

        .status-absent-with-reason {
            color: #3b82f6;
            /* Blue color */
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

        .absence-critical {
            color: var(--danger-color);
            font-weight: bold;
        }

        .absence-warning {
            color: #3b82f6;
            font-weight: bold;
        }

        .attendance-remark {
            font-size: 1.4rem;
            font-weight: 700;
            margin-top: 5px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 4px 0;
        }

        .attendance-days {
            display: block;
            font-size: 0.85rem;
            font-weight: 400;
            margin-top: 2px;
            color: var(--text-secondary);
            direction: rtl;
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

        .student-name.absent {
            color: var(--danger-color);
        }

        .student-name.pending {
            color: var(--warning-color);
        }

        .absence-with-reason {
            font-size: 0.8rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-left: 5px;
            background-color: var(--bg-secondary);
            padding: 2px 8px;
            border-radius: 4px;
            color: var(--warning-color);
            min-width: 80px;
            text-align: center;
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

        /* Adjust column widths for the simplified table */
        .export-table th:nth-child(2),
        .export-table td:nth-child(2) {
            width: 40%;
            text-align: right;
        }

        .export-table th:nth-child(3),
        .export-table td:nth-child(3) {
            width: 20%;
        }

        .export-table th:nth-child(4),
        .export-table td:nth-child(4) {
            width: 40%;
        }

        /* Adjust column widths with attendance remark column */
        .export-table.with-attendance th:nth-child(2),
        .export-table.with-attendance td:nth-child(2) {
            width: 30%;
            text-align: right;
        }

        .export-table.with-attendance th:nth-child(3),
        .export-table.with-attendance td:nth-child(3) {
            width: 15%;
        }

        .export-table.with-attendance th:nth-child(4),
        .export-table.with-attendance td:nth-child(4) {
            width: 35%;
        }

        .export-table.with-attendance th:nth-child(5),
        .export-table.with-attendance td:nth-child(5) {
            width: 20%;
        }

        .attendance-hint {
            font-size: 1.2rem;
            color: var(--text-secondary);
            text-align: center;
            margin-top: 10px;
            padding: 8px;
            border-top: 1px solid var(--border-color);
            direction: rtl;
        }
    </style>

    @php
        $showAttendanceRemark = $showAttendanceRemark ?? false;

        $chunks = $students->chunk(50);
        $totalPages = $chunks->count();
    @endphp

    @foreach ($chunks as $pageIndex => $studentsChunk)
        <div class="table-page" data-page="{{ $pageIndex + 1 }}">
            <table class="export-table {{ $showAttendanceRemark ? 'with-attendance' : '' }}">
                <thead>
                    <tr>
                        <th class="index-column">#</th>
                        <th>الاسم</th>
                        <th>تسجيل الحضور</th>
                        @if ($showAttendanceRemark)
                            <th>المواظبة </th>
                        @endif
                        <th>ملاحظات</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($studentsChunk as $index => $student)
                        @php
                            $todayProgress = $student->today_progress;
                            $consecutiveAbsentDays = $student->consecutiveAbsentDays;
                            $absenceStatus = $student->absenceStatus;
                            $status = $todayProgress?->status ?? 'pending';
                            if ($status === 'absent' && $todayProgress?->with_reason) {
                                $status = 'absent-with-reason';
                            }
                            $attendanceRemark = $student->attendanceRemark;
                        @endphp
                        <tr>
                            <td class="index-column">{{ $pageIndex * 25 + $index + 1 }}</td>
                            <td>
                                <span class="student-name {{ $status }}">{{ $student->name }}</span>
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
                                                @if ($todayProgress->with_reason)
                                                    <span class="status-absent-with-reason">
                                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none"
                                                            viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                        </svg>
                                                        <span class="absence-with-reason">غياب مبرر</span>
                                                    </span>
                                                @endif
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
                            @if ($showAttendanceRemark)
                                <td>
                                    @if ($attendanceRemark['label'])
                                        <span class="attendance-remark" style="color: {{ $attendanceRemark['color'] }}">
                                            {{ $attendanceRemark['label'] }}
                                            {{-- @if ($attendanceRemark['days'] !== null)
                                                <span class="attendance-days">
                                                    {{ $attendanceRemark['days'] }} يوم
                                                </span>
                                            @endif --}}
                                        </span>
                                    @endif
                                </td>
                            @endif
                            <td>
                                @if ($absenceStatus === 'critical')
                                    <span class="absence-critical">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                            stroke-width="2" stroke="currentColor" class="w-6 h-6">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                                        </svg>
                                        <small>إنذار ثاني ( أكثر من 3 أيام )</small>
                                    </span>
                                @elseif ($absenceStatus === 'warning')
                                    <span class="absence-warning">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                            stroke-width="2" stroke="currentColor" class="w-6 h-6">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                                        </svg>
                                        <small>إنذار أول (يومان غياب متتاليان)</small>
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
