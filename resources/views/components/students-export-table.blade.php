<div class="export-table-container" style="direction: rtl; font-family: 'Changa', sans-serif;">
    <style>
        .export-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            overflow: hidden;
        }
        
        .export-table th,
        .export-table td {
            border: 1px solid #e5e7eb;
            padding: 14px;
            text-align: right;
        }
        
        .export-table th {
            background-color: #f3f4f6;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.9rem;
            color: #374151;
        }
        
        .export-table tr:nth-child(even) {
            background-color: #f9fafb;
        }

        .export-table tr:hover {
            background-color: #f3f4f6;
        }
        
        .status-memorized {
            color: #059669;
            font-weight: 600;
            background-color: #d1fae5;
            padding: 4px 8px;
            border-radius: 4px;
            display: inline-block;
        }
        
        .status-absent {
            color: #dc2626;
            font-weight: 600;
            background-color: #fee2e2;
            padding: 4px 8px;
            border-radius: 4px;
            display: inline-block;
        }
        
        .status-pending {
            color: #d97706;
            font-weight: 600;
            background-color: #fef3c7;
            padding: 4px 8px;
            border-radius: 4px;
            display: inline-block;
        }

        .consecutive-absent {
            background-color: #fee2e2;
            color: #dc2626;
            font-weight: 600;
            padding: 4px 8px;
            border-radius: 4px;
        }

        .student-name {
            font-weight: 600;
            font-size: 1.1rem;
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
            font-size: 0.9rem;
        }

        .warning-icon {
            color: #dc2626;
            margin-left: 4px;
        }

        .index-column {
            width: 50px;
            text-align: center;
            color: #6b7280;
            font-weight: 600;
        }
    </style>

    <table class="export-table">
        <thead>
            <tr>
                <th class="index-column">#</th>
                <th>الاسم</th>
                <th>رقم الهاتف</th>
                <th>الحالة اليوم</th>
                <th>ملاحظات</th>
            </tr>
        </thead>
        <tbody>
            @foreach($students as $index => $student)
                @php
                    $todayProgress = $student->progresses->where('date', now()->format('Y-m-d'))->first();
                    $consecutiveAbsent = $student->progresses
                        ->where('date', '>=', now()->subDays(3)->format('Y-m-d'))
                        ->where('status', 'absent')
                        ->count() >= 3;
                    $status = $todayProgress?->status ?? 'pending';
                @endphp
                <tr>
                    <td class="index-column">{{ $index + 1 }}</td>
                    <td>
                        <span class="student-name {{ $status }}">{{ $student->name }}</span>
                    </td>
                    <td>
                        @if($consecutiveAbsent)
                            <span class="consecutive-absent">
                                <i class="fas fa-exclamation-triangle warning-icon"></i>
                                {{ $student->phone }}
                            </span>
                        @else
                            <span class="phone-number">{{ $student->phone }}</span>
                        @endif
                    </td>
                    <td>
                        <span class="status-{{ $status }}">
                            @if(!$todayProgress)
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
                        @if($consecutiveAbsent)
                            <span class="consecutive-absent">
                                <i class="fas fa-exclamation-triangle warning-icon"></i>
                                غائب لثلاثة أيام متتالية
                            </span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>