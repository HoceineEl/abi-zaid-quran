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
            --info-color: #0ea5e9;
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
            --info-color: #0ea5e9;
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
            padding: 12px;
            text-align: center;
            vertical-align: middle;
            font-size: 0.85rem;
        }

        .export-table th {
            background-color: var(--bg-header);
            font-weight: 700;
            font-size: 0.9rem;
            white-space: nowrap;
            color: var(--text-primary);
            padding: 14px 12px;
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
            justify-content: center;
            gap: 4px;
            padding: 4px 8px;
            border-radius: 3px;
            background-color: var(--bg-secondary);
        }

        .status-icon svg {
            width: 14px;
            height: 14px;
            stroke-width: 2;
            stroke: currentColor;
        }

        .status-disconnected {
            color: var(--danger-color);
        }

        .status-contacted {
            color: var(--warning-color);
        }

        .status-responded {
            color: var(--info-color);
        }

        .disconnection-duration {
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 3px;
            padding: 4px 10px;
            border-radius: 3px;
            background-color: var(--bg-secondary);
            min-width: 40px;
        }

        .disconnection-duration.short {
            color: var(--success-color);
            background-color: rgba(5, 150, 105, 0.1);
        }

        .disconnection-duration.medium {
            color: var(--warning-color);
            background-color: rgba(217, 119, 6, 0.1);
        }

        .disconnection-duration.long {
            color: var(--danger-color);
            background-color: rgba(220, 38, 38, 0.1);
        }

        .student-name {
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--text-primary);
            margin-bottom: 2px;
        }

        .group-name {
            color: var(--text-secondary);
            font-size: 0.75rem;
            font-weight: 500;
        }

        .phone-number {
            color: var(--text-secondary);
            font-size: 0.75rem;
            direction: ltr;
            font-family: monospace;
            background-color: var(--bg-secondary);
            padding: 1px 4px;
            border-radius: 2px;
            margin-top: 2px;
            display: inline-block;
        }

        .index-column {
            width: 25px;
            text-align: center;
            color: var(--text-secondary);
            font-size: 0.7rem;
            font-weight: 600;
            background-color: var(--bg-secondary);
            border-radius: 2px;
        }

        .contact-status {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 3px;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: 600;
            min-width: 60px;
            text-align: center;
            line-height: 1.2;
            white-space: nowrap;
        }

        .contact-status.contacted {
            background-color: var(--warning-color);
            color: white;
        }

        .contact-status.not-contacted {
            background-color: var(--danger-color);
            color: white;
        }

        .contact-status.responded {
            background-color: var(--info-color);
            color: white;
        }

        .message-response {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 3px;
            padding: 4px 10px;
            border-radius: 3px;
            font-size: 0.65rem;
            font-weight: 600;
            min-width: 50px;
            text-align: center;
            line-height: 1.1;
            white-space: nowrap;
            margin-top: 2px;
        }

        .message-response.yes {
            background-color: var(--success-color);
            color: white;
        }

        .message-response.no {
            background-color: var(--danger-color);
            color: white;
        }

        .message-response.not-contacted {
            background-color: var(--text-secondary);
            color: white;
        }

        .notes {
            font-size: 0.7rem;
            color: var(--text-secondary);
            text-align: center;
            max-width: 120px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            padding: 2px 4px;
            background-color: var(--bg-secondary);
            border-radius: 2px;
        }

        /* Column width adjustments */
        .export-table th:nth-child(2),
        .export-table td:nth-child(2) {
            width: 25%;
            text-align: right;
        }

        .export-table th:nth-child(3),
        .export-table td:nth-child(3) {
            width: 15%;
        }

        .export-table th:nth-child(4),
        .export-table td:nth-child(4) {
            width: 15%;
        }

        .export-table th:nth-child(5),
        .export-table td:nth-child(5) {
            width: 15%;
        }

        .export-table th:nth-child(6),
        .export-table td:nth-child(6) {
            width: 15%;
        }

        .export-table th:nth-child(7),
        .export-table td:nth-child(7) {
            width: 15%;
        }
    </style>

    @php
        $chunks = $disconnections->chunk(15);
        $totalPages = $chunks->count();
    @endphp

    @foreach ($chunks as $pageIndex => $disconnectionsChunk)
        <div class="table-page" data-page="{{ $pageIndex + 1 }}">
            <table class="export-table">
                <thead>
                    <tr>
                        <th class="index-column">#</th>
                        <th>اسم الطالب</th>
                        <th>المجموعة</th>
                        <th>تاريخ الانقطاع</th>
                        <th>مدة الانقطاع</th>
                        <th>حالة التواصل</th>
                        <th>ملاحظات</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($disconnectionsChunk as $index => $disconnection)
                        @php
                            $daysSinceLastPresent = $disconnection->student->getDaysSinceLastPresentAttribute();
                            $durationClass = match (true) {
                                $daysSinceLastPresent <= 7 => 'short',
                                $daysSinceLastPresent <= 14 => 'medium',
                                default => 'long',
                            };
                            $contactStatus = $disconnection->contact_date ? 'contacted' : 'not-contacted';
                            $messageResponse = $disconnection->message_response;
                        @endphp
                        <tr>
                            <td class="index-column">{{ $pageIndex * 15 + $index + 1 }}</td>
                            <td>
                                <span class="student-name">{{ $disconnection->student->name }}</span>
                                <br>
                                <span class="phone-number">{{ $disconnection->student->phone }}</span>
                            </td>
                            <td>
                                <span class="group-name">{{ $disconnection->group->name }}</span>
                            </td>
                            <td>
                                <span class="status-disconnected status-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                        stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                                    </svg>
                                    {{ $disconnection->disconnection_date->format('Y-m-d') }}
                                </span>
                            </td>
                            <td>
                                <span class="disconnection-duration {{ $durationClass }}">
                                    @if ($daysSinceLastPresent === null)
                                        <span>غير محدد</span>
                                    @else
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                            stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                        </svg>
                                        {{ $daysSinceLastPresent }} يوم
                                    @endif
                                </span>
                            </td>
                            <td>
                                @if ($disconnection->contact_date)
                                    <span class="contact-status contacted">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                            stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z" />
                                        </svg>
                                        تم التواصل
                                    </span>
                                    @if ($messageResponse)
                                        <br>
                                        <span
                                            class="message-response {{ $messageResponse === 'yes' ? 'yes' : ($messageResponse === 'no' ? 'no' : 'not-contacted') }}">
                                            @if ($messageResponse === 'yes')
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none"
                                                    viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                                </svg>
                                                رد بالإيجاب
                                            @elseif ($messageResponse === 'no')
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none"
                                                    viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                رد بالسلب
                                            @else
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none"
                                                    viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                                </svg>
                                                لم يرد
                                            @endif
                                        </span>
                                    @endif
                                @else
                                    <span class="contact-status not-contacted">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                            stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                                        </svg>
                                        لم يتم التواصل
                                    </span>
                                @endif
                            </td>
                            <td>
                                @if ($disconnection->notes)
                                    <span class="notes" title="{{ $disconnection->notes }}">
                                        {{ Str::limit($disconnection->notes, 50) }}
                                    </span>
                                @else
                                    <span class="notes">-</span>
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
