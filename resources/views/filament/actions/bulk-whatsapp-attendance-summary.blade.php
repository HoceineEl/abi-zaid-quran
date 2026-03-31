@php
    $items = [
        ['label' => 'مجموعات جاهزة', 'value' => $totals['ready_group_count'] ?? 0, 'color' => 'text-success-700 bg-success-50'],
        ['label' => 'مجموعات متخطاة', 'value' => $totals['skipped_group_count'] ?? 0, 'color' => 'text-danger-700 bg-danger-50'],
        ['label' => 'إجمالي الطلاب', 'value' => $totals['total_students'] ?? 0, 'color' => 'text-gray-700 bg-gray-50'],
        ['label' => 'مطابقون من واتساب', 'value' => $totals['matched_students'] ?? 0, 'color' => 'text-primary-700 bg-primary-50'],
        ['label' => 'مسجلون حضوراً مسبقاً', 'value' => $totals['already_present_students'] ?? 0, 'color' => 'text-info-700 bg-info-50'],
        ['label' => 'سيتم تسجيلهم حضوراً', 'value' => $totals['to_mark_present_students'] ?? 0, 'color' => 'text-success-700 bg-success-50'],
        ['label' => 'المتبقون بدون تسجيل', 'value' => $totals['remaining_students'] ?? 0, 'color' => 'text-warning-700 bg-warning-50'],
        ['label' => 'التذكيرات المخطط لها', 'value' => $totals['planned_reminders'] ?? 0, 'color' => 'text-info-700 bg-info-50'],
    ];
@endphp

<div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
    @foreach ($items as $item)
        <div class="rounded-xl px-3 py-2 {{ $item['color'] }}">
            <div class="text-xs font-medium opacity-80">{{ $item['label'] }}</div>
            <div class="mt-1 text-lg font-semibold">{{ $item['value'] }}</div>
        </div>
    @endforeach
</div>

@if (($totals['planned_invalid_reminder_phones'] ?? 0) > 0)
    <div class="mt-3 rounded-xl border border-warning-200 bg-warning-50 px-3 py-2 text-sm text-warning-800">
        سيتم تخطي {{ $totals['planned_invalid_reminder_phones'] }} طالب في التذكيرات لعدم توفر رقم واتساب صالح.
    </div>
@endif
