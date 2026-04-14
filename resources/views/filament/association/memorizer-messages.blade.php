<div class="space-y-3">
    @forelse($messages as $message)
        <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-3">
            <div class="flex items-center justify-between mb-2">
                <span @class([
                    'inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset',
                    'bg-yellow-50 text-yellow-700 ring-yellow-600/20 dark:bg-yellow-400/10 dark:text-yellow-500 dark:ring-yellow-400/20' => $message->status->value === 'queued',
                    'bg-green-50 text-green-700 ring-green-600/20 dark:bg-green-400/10 dark:text-green-500 dark:ring-green-400/20' => $message->status->value === 'sent',
                    'bg-red-50 text-red-700 ring-red-600/20 dark:bg-red-400/10 dark:text-red-500 dark:ring-red-400/20' => $message->status->value === 'failed',
                    'bg-gray-50 text-gray-700 ring-gray-600/20 dark:bg-gray-400/10 dark:text-gray-500 dark:ring-gray-400/20' => $message->status->value === 'cancelled',
                ])>
                    {{ $message->status->getLabel() }}
                </span>
                <span class="text-xs text-gray-500 dark:text-gray-400" dir="ltr">
                    {{ $message->created_at->format('Y-m-d H:i') }}
                </span>
            </div>
            <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-line">{{ Str::limit($message->message_content, 200) }}</p>
            @if($message->error_message)
                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message->error_message }}</p>
            @endif
        </div>
    @empty
        <div class="text-center py-6 text-gray-500 dark:text-gray-400">
            <x-heroicon-o-envelope class="mx-auto h-8 w-8 mb-2" />
            <p>لا توجد رسائل مرسلة لهذا الطالب</p>
        </div>
    @endforelse
</div>
