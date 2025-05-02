@props(['students', 'group', 'dateRange', 'statusPerDay'])

<div class="overflow-x-auto">
    <div class="table-page" data-page="1">
        <table class="divide-y divide-gray-200 dark:divide-gray-700 border border-gray-200 dark:border-gray-700">
            <thead>
                <tr class="bg-gray-50 dark:bg-gray-800">
                    <th scope="col"
                        class="px-4 py-4 text-right text-sm font-semibold text-gray-900 dark:text-gray-100 whitespace-nowrap border-b border-gray-200 dark:border-gray-700">
                        الطالب
                    </th>
                    @foreach ($dateRange as $date)
                        @php
                            $formattedDate = $date->format('Y-m-d');
                            $displayDate = $date->format('d/m');
                        @endphp
                        <th scope="col"
                            class="px-4 py-4 text-center text-sm font-semibold text-gray-900 dark:text-gray-100 whitespace-nowrap border-b border-gray-200 dark:border-gray-700">
                            {{ $displayDate }}
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                @foreach ($students as $index => $student)
                    <tr class="{{ $index % 2 === 0 ? 'bg-white dark:bg-gray-900' : 'bg-gray-50 dark:bg-gray-800' }}">
                        <td
                            class="whitespace-nowrap px-4 py-4 text-sm text-gray-900 dark:text-gray-100 border-r border-gray-200 dark:border-gray-700">
                            {{ $loop->iteration }}. {{ $student->name }}
                        </td>
                        @foreach ($dateRange as $date)
                            @php
                                $formattedDate = $date->format('Y-m-d');
                                $progress = isset($statusPerDay[$student->id][$formattedDate])
                                    ? $statusPerDay[$student->id][$formattedDate]->first()[0]
                                    : null;
                                $status = $progress ? $progress->status : null;
                                $withReason = $progress && $status === 'absent' ? $progress->with_reason : false;

                                $icon = match ($status) {
                                    'memorized'
                                        => '<svg class="w-5 h-5 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>',
                                    'absent'
                                        => '<svg class="w-5 h-5 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>' .
                                        ($withReason ? '<span class="text-xs block mt-1">(مبرر)</span>' : ''),
                                    default
                                        => '<svg class="w-5 h-5 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>',
                                };

                                $statusColor = match (true) {
                                    $status === 'memorized' => 'text-green-600 dark:text-green-400',
                                    $status === 'absent' && $withReason => 'text-orange-500 dark:text-orange-400',
                                    $status === 'absent' => 'text-red-600 dark:text-red-400',
                                    default => 'text-gray-400 dark:text-gray-500',
                                };
                            @endphp
                            <td
                                class="whitespace-nowrap px-4 py-4 text-sm text-center {{ $statusColor }} border-r border-gray-200 dark:border-gray-700">
                                {!! $icon !!}
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
