<x-filament-panels::page>
    <div
        class="fi-section rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
    >
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            {{-- Preset range buttons --}}
            <div class="flex flex-wrap items-center gap-2">
                @foreach ($this->presets() as $key => $label)
                    <x-filament::button
                        size="sm"
                        wire:click="applyPreset('{{ $key }}')"
                        :color="$preset === $key ? 'primary' : 'gray'"
                        :outlined="$preset !== $key"
                        wire:loading.attr="disabled"
                    >
                        {{ $label }}
                    </x-filament::button>
                @endforeach
            </div>

            {{-- Custom month range --}}
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-sm font-medium text-gray-600 dark:text-gray-300">نطاق مخصص:</span>
                <input
                    type="month"
                    wire:model.live="customFrom"
                    class="fi-input block rounded-lg border-gray-300 bg-white text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-gray-800 dark:text-white"
                />
                <span class="text-gray-400">—</span>
                <input
                    type="month"
                    wire:model.live="customTo"
                    class="fi-input block rounded-lg border-gray-300 bg-white text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-gray-800 dark:text-white"
                />
            </div>
        </div>

        {{-- Legend + count --}}
        <div class="mt-3 flex flex-wrap items-center gap-4 border-t border-gray-100 pt-3 text-xs text-gray-500 dark:border-white/5 dark:text-gray-400">
            <span class="font-medium text-gray-700 dark:text-gray-200">
                {{ count($this->monthsInRange()) }} شهر معروض
            </span>
            <span class="inline-flex items-center gap-1">
                <x-filament::icon icon="heroicon-s-check-circle" class="h-4 w-4 text-success-500" />
                مدفوع
            </span>
            <span class="inline-flex items-center gap-1">
                <x-filament::icon icon="heroicon-o-x-circle" class="h-4 w-4 text-danger-500" />
                غير مدفوع
            </span>
            <span class="inline-flex items-center gap-1">
                <x-filament::icon icon="heroicon-o-minus-circle" class="h-4 w-4 text-gray-400" />
                معفى
            </span>
            <span class="text-gray-400">انقر على خانة الشهر لتسجيل الدفع أو طباعة الإيصال.</span>
        </div>
    </div>

    {{ $this->table }}
</x-filament-panels::page>
