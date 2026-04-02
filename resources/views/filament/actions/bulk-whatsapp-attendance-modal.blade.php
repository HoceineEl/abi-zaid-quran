@php
    $totals = $preview['totals'] ?? [];
    $groups = $preview['groups'] ?? [];

    $compactNames = static function (array $names, int $limit = 4): string {
        if (blank($names)) return '—';
        $visible = array_slice($names, 0, $limit);
        $remaining = count($names) - count($visible);
        $text = implode('، ', $visible);
        return $remaining > 0 ? "{$text} +{$remaining}" : $text;
    };
@endphp

<div class="space-y-3 pb-1" dir="rtl">

    {{-- Date header --}}
    <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
        <x-heroicon-o-calendar class="h-4 w-4 shrink-0" />
        <span>{{ \Carbon\Carbon::parse($date)->translatedFormat('l، j F Y') }}</span>
    </div>

    {{-- Summary stats: always one row --}}
    <div class="flex gap-2">
        <div class="flex-1 rounded-xl bg-success-50 px-3 py-2.5 dark:bg-success-500/10">
            <div class="text-xs font-medium text-success-700 dark:text-success-400">جاهزة</div>
            <div class="mt-0.5 text-xl font-bold leading-none text-success-700 dark:text-success-400">{{ $totals['ready_group_count'] ?? 0 }}</div>
        </div>
        <div class="flex-1 rounded-xl bg-primary-50 px-3 py-2.5 dark:bg-primary-500/10">
            <div class="text-xs font-medium text-primary-700 dark:text-primary-400">سيُسجَّلون</div>
            <div class="mt-0.5 text-xl font-bold leading-none text-primary-700 dark:text-primary-400">{{ $totals['to_mark_present_students'] ?? 0 }}</div>
        </div>
        <div class="flex-1 rounded-xl bg-warning-50 px-3 py-2.5 dark:bg-warning-500/10">
            <div class="text-xs font-medium text-warning-700 dark:text-warning-400">متبقون</div>
            <div class="mt-0.5 text-xl font-bold leading-none text-warning-700 dark:text-warning-400">{{ $totals['remaining_students'] ?? 0 }}</div>
        </div>
        <div class="flex-1 rounded-xl bg-danger-50 px-3 py-2.5 dark:bg-danger-500/10">
            <div class="text-xs font-medium text-danger-700 dark:text-danger-400">متخطاة</div>
            <div class="mt-0.5 text-xl font-bold leading-none text-danger-700 dark:text-danger-400">{{ $totals['skipped_group_count'] ?? 0 }}</div>
        </div>
    </div>

    {{-- Extra planned info row --}}
    @if (($totals['planned_absent_students'] ?? 0) > 0 || ($totals['planned_reminders'] ?? 0) > 0)
        <div class="flex flex-wrap gap-2">
            @if (($totals['planned_absent_students'] ?? 0) > 0)
                <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700 dark:bg-white/10 dark:text-gray-300">
                    <x-heroicon-o-x-circle class="h-3.5 w-3.5" />
                    {{ $totals['planned_absent_students'] }} سيُسجَّلون غائبين
                </span>
            @endif
            @if (($totals['planned_reminders'] ?? 0) > 0)
                <span class="inline-flex items-center gap-1 rounded-full bg-info-50 px-2.5 py-1 text-xs font-medium text-info-700 dark:bg-info-500/10 dark:text-info-300">
                    <x-heroicon-o-bell class="h-3.5 w-3.5" />
                    {{ $totals['planned_reminders'] }} تذكير مجدوَل
                </span>
            @endif
            @if (($totals['planned_invalid_reminder_phones'] ?? 0) > 0)
                <span class="inline-flex items-center gap-1 rounded-full bg-warning-50 px-2.5 py-1 text-xs font-medium text-warning-700 dark:bg-warning-500/10 dark:text-warning-300">
                    <x-heroicon-o-exclamation-triangle class="h-3.5 w-3.5" />
                    {{ $totals['planned_invalid_reminder_phones'] }} رقم غير صالح
                </span>
            @endif
        </div>
    @endif

    {{-- Per-group cards --}}
    <div class="max-h-72 space-y-2 overflow-y-auto sm:max-h-96">
        @forelse ($groups as $group)
            @php
                $isReady        = ($group['status'] ?? 'ready') === 'ready';
                $toMarkCount    = count($group['to_mark_present_ids'] ?? []);
                $alreadyCount   = count($group['already_present_ids'] ?? []);
                $remainingCount = count($group['remaining_student_ids'] ?? []);
                $matchedCount   = count($group['matched_student_ids'] ?? []);
            @endphp

            <div class="rounded-2xl border bg-white p-3 shadow-sm dark:bg-gray-900
                        {{ $isReady ? 'border-gray-200 dark:border-gray-800' : 'border-danger-200 dark:border-danger-800/50' }}">

                {{-- Group name + status --}}
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-gray-900 dark:text-white">
                            {{ $group['group_name'] }}
                        </p>
                        @if ($isReady)
                            <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">
                                {{ $group['submission_type_label'] ?? '' }} · {{ $group['total_students'] }} طالب
                            </p>
                        @endif
                    </div>
                    @if ($isReady)
                        <span class="shrink-0 rounded-full bg-success-50 px-2 py-0.5 text-xs font-medium text-success-700 dark:bg-success-500/10 dark:text-success-400">
                            جاهزة
                        </span>
                    @else
                        <span class="shrink-0 rounded-full bg-danger-50 px-2 py-0.5 text-xs font-medium text-danger-700 dark:bg-danger-500/10 dark:text-danger-400">
                            متخطاة
                        </span>
                    @endif
                </div>

                @if (! $isReady)
                    <p class="mt-2 text-xs text-danger-600 dark:text-danger-400">{{ $group['skip_reason'] }}</p>
                @else
                    {{-- 4-stat row --}}
                    <div class="mt-2.5 grid grid-cols-4 gap-1.5 text-center text-xs">
                        <div class="rounded-lg bg-primary-50 py-1.5 dark:bg-primary-500/10">
                            <div class="font-bold text-primary-700 dark:text-primary-400">{{ $matchedCount }}</div>
                            <div class="mt-0.5 text-primary-600/70 dark:text-primary-400/70">مطابق</div>
                        </div>
                        <div class="rounded-lg bg-info-50 py-1.5 dark:bg-info-500/10">
                            <div class="font-bold text-info-700 dark:text-info-400">{{ $alreadyCount }}</div>
                            <div class="mt-0.5 text-info-600/70 dark:text-info-400/70">سابق</div>
                        </div>
                        <div class="rounded-lg bg-success-50 py-1.5 dark:bg-success-500/10">
                            <div class="font-bold text-success-700 dark:text-success-400">{{ $toMarkCount }}</div>
                            <div class="mt-0.5 text-success-600/70 dark:text-success-400/70">سيُسجَّل</div>
                        </div>
                        <div class="rounded-lg bg-warning-50 py-1.5 dark:bg-warning-500/10">
                            <div class="font-bold text-warning-700 dark:text-warning-400">{{ $remainingCount }}</div>
                            <div class="mt-0.5 text-warning-600/70 dark:text-warning-400/70">متبقٍ</div>
                        </div>
                    </div>

                    {{-- Names (only shown when there's data) --}}
                    @if ($toMarkCount > 0 || $remainingCount > 0)
                        <div class="mt-2 grid grid-cols-1 gap-1.5 sm:grid-cols-2">
                            @if ($toMarkCount > 0)
                                <div class="rounded-lg bg-gray-50 px-2.5 py-1.5 dark:bg-gray-800/60">
                                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">سيُسجَّلون حضوراً</p>
                                    <p class="mt-0.5 text-xs text-gray-700 dark:text-gray-200 leading-5">
                                        {{ $compactNames($group['to_mark_present_names'] ?? []) }}
                                    </p>
                                </div>
                            @endif
                            @if ($remainingCount > 0)
                                <div class="rounded-lg bg-gray-50 px-2.5 py-1.5 dark:bg-gray-800/60">
                                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">المتبقون</p>
                                    <p class="mt-0.5 text-xs text-gray-700 dark:text-gray-200 leading-5">
                                        {{ $compactNames($group['remaining_student_names'] ?? []) }}
                                    </p>
                                    @if (($group['remaining_invalid_phone_count'] ?? 0) > 0)
                                        <p class="mt-1 text-xs text-warning-600 dark:text-warning-400">
                                            {{ $group['remaining_invalid_phone_count'] }} بدون رقم صالح
                                        </p>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endif
                @endif
            </div>
        @empty
            <div class="rounded-2xl border border-dashed border-gray-200 bg-gray-50 px-4 py-8 text-center text-sm text-gray-400 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-500">
                لا توجد مجموعات متاحة.
            </div>
        @endforelse
    </div>

</div>
