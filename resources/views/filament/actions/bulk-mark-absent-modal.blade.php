<div class="space-y-2" dir="rtl">
    <p class="text-sm font-medium text-gray-700 dark:text-gray-200">
        سيتم تسجيل <strong class="text-danger-600">{{ $total }}</strong> طالب كغائب في تاريخ {{ $date }}:
    </p>
    <div class="space-y-1.5 mt-2">
        @foreach ($groups as $g)
            <div class="flex items-center justify-between rounded-xl bg-gray-50 px-3 py-2 dark:bg-gray-800/60">
                <span class="text-sm text-gray-700 dark:text-gray-200">{{ $g['name'] }}</span>
                <span class="rounded-full bg-danger-50 px-2 py-0.5 text-xs font-bold text-danger-700 dark:bg-danger-500/10 dark:text-danger-400">{{ $g['count'] }}</span>
            </div>
        @endforeach
    </div>
</div>
