<div class="space-y-2" dir="rtl">
    <p class="text-sm font-medium text-gray-700 dark:text-gray-200">
        سيتم إرسال <strong class="text-warning-600">{{ $totalSend }}</strong> تذكير في تاريخ {{ $date }}:
    </p>
    <div class="space-y-1.5 mt-2">
        @foreach ($groups as $g)
            <div class="rounded-xl bg-gray-50 px-3 py-2 dark:bg-gray-800/60">
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-700 dark:text-gray-200">{{ $g['name'] }}</span>
                    <div class="flex gap-1.5">
                        <span class="rounded-full bg-warning-50 px-2 py-0.5 text-xs font-bold text-warning-700 dark:bg-warning-500/10 dark:text-warning-400">{{ $g['with_phone'] }} تذكير</span>
                        @if ($g['without_phone'] > 0)
                            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-500 dark:bg-white/10">{{ $g['without_phone'] }} بدون رقم</span>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
    @if ($totalSkip > 0)
        <p class="mt-2 text-xs text-gray-400">سيتم تخطي {{ $totalSkip }} طالب بدون رقم واتساب صالح.</p>
    @endif
</div>
