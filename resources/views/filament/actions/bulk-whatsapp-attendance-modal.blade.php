@php
    $totals = $preview['totals'] ?? [];
    $groups = $preview['groups'] ?? [];

    $compactNames = static function (array $names, int $limit = 5): string {
        if (blank($names)) {
            return 'لا يوجد';
        }

        $visible = array_slice($names, 0, $limit);
        $remaining = count($names) - count($visible);
        $text = implode('، ', $visible);

        if ($remaining > 0) {
            $text .= " +{$remaining}";
        }

        return $text;
    };

    $summaryItems = [
        ['label' => 'جاهزة', 'value' => $totals['ready_group_count'] ?? 0, 'classes' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300'],
        ['label' => 'متخطاة', 'value' => $totals['skipped_group_count'] ?? 0, 'classes' => 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300'],
        ['label' => 'مطابقون', 'value' => $totals['matched_students'] ?? 0, 'classes' => 'bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300'],
        ['label' => 'سيُسجلون', 'value' => $totals['to_mark_present_students'] ?? 0, 'classes' => 'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300'],
        ['label' => 'المتبقون', 'value' => $totals['remaining_students'] ?? 0, 'classes' => 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300'],
        ['label' => 'التذكيرات', 'value' => $totals['planned_reminders'] ?? 0, 'classes' => 'bg-slate-100 text-slate-700 dark:bg-white/5 dark:text-slate-200'],
    ];
@endphp

<div class="space-y-4">
    <div class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div class="min-w-0">
                <h3 class="text-base font-semibold text-gray-950 dark:text-white">مراجعة الحضور الجماعي</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">التاريخ: {{ $date }}</p>
            </div>
        </div>

        <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($summaryItems as $item)
                <div class="rounded-xl px-4 py-3 {{ $item['classes'] }}">
                    <div class="text-sm font-medium opacity-80">{{ $item['label'] }}</div>
                    <div class="mt-2 text-2xl font-semibold leading-none">{{ $item['value'] }}</div>
                </div>
            @endforeach
        </div>

        @if (($totals['planned_invalid_reminder_phones'] ?? 0) > 0)
            <p class="mt-3 text-sm text-amber-700 dark:text-amber-300">
                سيتم تخطي {{ $totals['planned_invalid_reminder_phones'] }} طالب في التذكيرات لعدم توفر رقم واتساب صالح.
            </p>
        @endif
    </div>

    <div class="max-h-[28rem] space-y-3 overflow-y-auto">
        @forelse ($groups as $group)
            @php
                $isReady = ($group['status'] ?? 'ready') === 'ready';
                $matchedCount = count($group['matched_student_ids'] ?? []);
                $alreadyPresentCount = count($group['already_present_ids'] ?? []);
                $toMarkCount = count($group['to_mark_present_ids'] ?? []);
                $remainingCount = count($group['remaining_student_ids'] ?? []);
            @endphp

            <div class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h4 class="truncate text-base font-semibold text-gray-950 dark:text-white">
                                {{ $group['group_name'] }}
                            </h4>

                            @if ($isReady)
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700 dark:bg-white/5 dark:text-slate-300">
                                    {{ $group['submission_type_label'] ?? '' }}
                                </span>
                            @else
                                <span class="rounded-full bg-rose-50 px-2.5 py-1 text-xs font-medium text-rose-700 dark:bg-rose-500/10 dark:text-rose-300">
                                    متخطاة
                                </span>
                            @endif
                        </div>

                        @if (! $isReady)
                            <p class="mt-2 text-sm text-rose-700 dark:text-rose-300">
                                {{ $group['skip_reason'] ?? 'لا يمكن معالجة هذه المجموعة حالياً.' }}
                            </p>
                        @endif
                    </div>
                </div>

                @if ($isReady)
                    <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <div class="rounded-xl bg-sky-50 px-4 py-3 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300">
                            <div class="text-sm font-medium opacity-80">مطابقون من واتساب</div>
                            <div class="mt-2 text-2xl font-semibold leading-none">{{ $matchedCount }}</div>
                        </div>
                        <div class="rounded-xl bg-slate-100 px-4 py-3 text-slate-700 dark:bg-white/5 dark:text-slate-200">
                            <div class="text-sm font-medium opacity-80">مسجلون مسبقاً</div>
                            <div class="mt-2 text-2xl font-semibold leading-none">{{ $alreadyPresentCount }}</div>
                        </div>
                        <div class="rounded-xl bg-emerald-50 px-4 py-3 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">
                            <div class="text-sm font-medium opacity-80">سيتم تسجيلهم</div>
                            <div class="mt-2 text-2xl font-semibold leading-none">{{ $toMarkCount }}</div>
                        </div>
                        <div class="rounded-xl bg-amber-50 px-4 py-3 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300">
                            <div class="text-sm font-medium opacity-80">المتبقون</div>
                            <div class="mt-2 text-2xl font-semibold leading-none">{{ $remainingCount }}</div>
                        </div>
                    </div>

                    <div class="mt-4 grid gap-3 xl:grid-cols-3">
                        <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-800 dark:bg-gray-950/50">
                            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">سيتم تسجيلهم</div>
                            <div class="mt-2 text-sm leading-7 text-gray-700 dark:text-gray-200">
                                {{ $compactNames($group['to_mark_present_names'] ?? []) }}
                            </div>
                        </div>
                        <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-800 dark:bg-gray-950/50">
                            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">مسجلون مسبقاً</div>
                            <div class="mt-2 text-sm leading-7 text-gray-700 dark:text-gray-200">
                                {{ $compactNames($group['already_present_names'] ?? []) }}
                            </div>
                        </div>
                        <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-800 dark:bg-gray-950/50">
                            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">المتبقون</div>
                            <div class="mt-2 text-sm leading-7 text-gray-700 dark:text-gray-200">
                                {{ $compactNames($group['remaining_student_names'] ?? []) }}
                            </div>

                            @if (($group['remaining_invalid_phone_count'] ?? 0) > 0)
                                <div class="mt-2 text-xs text-amber-700 dark:text-amber-300">
                                    {{ $group['remaining_invalid_phone_count'] }} بدون رقم واتساب صالح
                                </div>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        @empty
            <div class="rounded-2xl border border-gray-200 bg-white px-4 py-10 text-center text-sm text-gray-500 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400">
                لا توجد مجموعات متاحة حالياً.
            </div>
        @endforelse
    </div>
</div>
