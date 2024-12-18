<div class="export-table-container" style="direction: rtl; font-family: 'Changa', sans-serif;">
    <style>
        .export-table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }

        .export-table th,
        .export-table td {
            border: 1px solid #e5e7eb;
            padding: 8px;
            text-align: right;
        }

        .export-table th {
            background-color: #f3f4f6;
            font-weight: 600;
            font-size: 0.9rem;
            color: #374151;
        }

        .export-table tr:nth-child(even) {
            background-color: #f9fafb;
        }

        .status-memorized {
            color: #059669;
        }

        .status-absent {
            color: #dc2626;
        }

        .status-pending {
            color: #d97706;
        }

        .consecutive-absent {
            color: #dc2626;
        }

        .student-name {
            font-weight: 600;
            font-size: 1.3rem;
        }

        .student-name.memorized {
            color: #059669;
        }

        .student-name.absent {
            color: #dc2626;
        }

        .student-name.pending {
            color: #d97706;
        }

        .phone-number {
            color: #6b7280;
            font-size: 1.1rem;
        }

        .index-column {
            width: 40px;
            text-align: center;
            color: #6b7280;
        }

        .city {
            color: #6b7280;
            font-size: 0.9rem;
        }
    </style>

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
            @foreach ($students as $index => $student)
                @php
                    $todayProgress = $student->progresses->where('date', now()->format('Y-m-d'))->first();
                    $consecutiveAbsentDays = $student->consecutiveAbsentDays;

                    $status = $todayProgress?->status ?? 'pending';
                @endphp
                <tr>
                    <td class="index-column">{{ $index + 1 }}</td>
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
                        <span class="status-{{ $status }}">
                            @if (!$todayProgress)
                                لم يسجل بعد
                            @else
                                @switch($todayProgress->status)
                                    @case('memorized')
                                        حاضر
                                    @break

                                    @case('absent')
                                        غائب
                                    @break

                                    @default
                                        قيد الانتظار
                                @endswitch
                            @endif
                        </span>
                    </td>
                    <td>
                        @if ($student->needsCall())
                            <span class="consecutive-absent">
                                يجب الاتصال (3 مرات متتالية)
                            </span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
