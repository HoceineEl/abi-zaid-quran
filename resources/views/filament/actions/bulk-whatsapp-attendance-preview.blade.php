@php
    $limitNames = static function (array $names, int $limit = 6): string {
        $visible = array_slice($names, 0, $limit);
        $remaining = count($names) - count($visible);
        $text = implode('، ', $visible);

        if ($remaining > 0) {
            $text .= "، +{$remaining}";
        }

        return $text;
    };
@endphp

<div class="max-h-[26rem] space-y-3 overflow-y-auto pe-1">
    @forelse ($groups as $group)
        <div class="rounded-2xl border border-gray-200 bg-white px-4 py-3 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <div class="text-sm font-semibold text-gray-950 dark:text-white">{{ $group['group_name'] }}</div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $group['submission_type_label'] ?? '' }}</div>
                </div>

                @if (($group['status'] ?? 'ready') === 'ready')
                    <span class="rounded-full bg-success-50 px-2.5 py-1 text-xs font-medium text-success-700 dark:bg-success-500/10 dark:text-success-400">
                        جاهزة
                    </span>
                @else
                    <span class="rounded-full bg-danger-50 px-2.5 py-1 text-xs font-medium text-danger-700 dark:bg-danger-500/10 dark:text-danger-400">
                        متخطاة
                    </span>
                @endif
            </div>

            @if (($group['status'] ?? 'ready') !== 'ready')
                <div class="mt-3 rounded-xl bg-danger-50 px-3 py-2 text-sm text-danger-700 dark:bg-danger-500/10 dark:text-danger-300">
                    {{ $group['skip_reason'] }}
                </div>
                @continue
            @endif

            <div class="mt-3 grid gap-2 sm:grid-cols-2 xl:grid-cols-5">
                <div class="rounded-xl bg-gray-50 px-3 py-2 text-sm dark:bg-gray-800/70">
                    <div class="text-xs text-gray-500 dark:text-gray-400">الطلاب</div>
                    <div class="mt-1 font-semibold text-gray-900 dark:text-white">{{ $group['total_students'] }}</div>
                </div>
                <div class="rounded-xl bg-primary-50 px-3 py-2 text-sm text-primary-700 dark:bg-primary-500/10 dark:text-primary-300">
                    <div class="text-xs opacity-80">مطابقون</div>
                    <div class="mt-1 font-semibold">{{ count($group['matched_student_ids'] ?? []) }}</div>
                </div>
                <div class="rounded-xl bg-info-50 px-3 py-2 text-sm text-info-700 dark:bg-info-500/10 dark:text-info-300">
                    <div class="text-xs opacity-80">حضور مسبق</div>
                    <div class="mt-1 font-semibold">{{ count($group['already_present_ids'] ?? []) }}</div>
                </div>
                <div class="rounded-xl bg-success-50 px-3 py-2 text-sm text-success-700 dark:bg-success-500/10 dark:text-success-300">
                    <div class="text-xs opacity-80">سيتم تسجيلهم</div>
                    <div class="mt-1 font-semibold">{{ count($group['to_mark_present_ids'] ?? []) }}</div>
                </div>
                <div class="rounded-xl bg-warning-50 px-3 py-2 text-sm text-warning-700 dark:bg-warning-500/10 dark:text-warning-300">
                    <div class="text-xs opacity-80">المتبقون</div>
                    <div class="mt-1 font-semibold">{{ count($group['remaining_student_ids'] ?? []) }}</div>
                </div>
            </div>

            <div class="mt-3 grid gap-2 xl:grid-cols-3">
                <div class="rounded-xl bg-gray-50 px-3 py-2 text-sm dark:bg-gray-800/70">
                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400">سيتم تسجيلهم حضوراً</div>
                    <div class="mt-1 text-gray-700 dark:text-gray-200">
                        {{ count($group['to_mark_present_names'] ?? []) ? $limitNames($group['to_mark_present_names']) : 'لا يوجد' }}
                    </div>
                </div>
                <div class="rounded-xl bg-gray-50 px-3 py-2 text-sm dark:bg-gray-800/70">
                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400">حضور مسبق</div>
                    <div class="mt-1 text-gray-700 dark:text-gray-200">
                        {{ count($group['already_present_names'] ?? []) ? $limitNames($group['already_present_names']) : 'لا يوجد' }}
                    </div>
                </div>
                <div class="rounded-xl bg-gray-50 px-3 py-2 text-sm dark:bg-gray-800/70">
                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400">المتبقون للتذكير</div>
                    <div class="mt-1 text-gray-700 dark:text-gray-200">
                        {{ count($group['remaining_student_names'] ?? []) ? $limitNames($group['remaining_student_names']) : 'لا يوجد' }}
                    </div>
                    @if (($group['remaining_invalid_phone_count'] ?? 0) > 0)
                        <div class="mt-1 text-xs text-warning-700 dark:text-warning-300">
                            {{ $group['remaining_invalid_phone_count'] }} بدون رقم صالح
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @empty
        <div class="rounded-2xl border border-gray-200 bg-gray-50 px-4 py-6 text-sm text-gray-600 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            لا توجد مجموعات متاحة حالياً.
        </div>
    @endforelse
</div>
