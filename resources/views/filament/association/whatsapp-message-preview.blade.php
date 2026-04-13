<div class="p-4 space-y-4">
    <div class="flex items-center gap-3 pb-3 border-b border-gray-200 dark:border-gray-700">
        <div class="flex-1 min-w-0">
            <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $record->recipient_name }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400" dir="ltr">{{ $record->recipient_phone }}</p>
        </div>
        <div>
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                {{ $record->status->getColor() === 'success' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' : '' }}
                {{ $record->status->getColor() === 'danger'  ? 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400' : '' }}
                {{ $record->status->getColor() === 'warning' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400' : '' }}
                {{ $record->status->getColor() === 'gray'    ? 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300' : '' }}
            ">
                {{ $record->status->getLabel() }}
            </span>
        </div>
    </div>

    <div class="bg-gray-50 dark:bg-gray-800 rounded-xl p-4 border border-gray-200 dark:border-gray-700">
        <p class="text-sm text-gray-800 dark:text-gray-200 whitespace-pre-wrap leading-relaxed" dir="auto">{{ $message }}</p>
    </div>

    @if($record->sent_at)
    <p class="text-xs text-gray-400 dark:text-gray-500 text-left">
        أُرسل في: {{ $record->sent_at->format('Y-m-d H:i') }}
    </p>
    @endif

    @if($record->error_message)
    <div class="bg-red-50 dark:bg-red-900/20 rounded-lg p-3 border border-red-200 dark:border-red-800">
        <p class="text-xs text-red-700 dark:text-red-400">{{ $record->error_message }}</p>
    </div>
    @endif
</div>
