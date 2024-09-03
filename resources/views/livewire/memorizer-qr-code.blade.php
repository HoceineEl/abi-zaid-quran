<div class="min-h-screen bg-gray-100 dark:bg-gray-900 p-4 sm:p-6 lg:p-8">
    <div class="max-w-7xl mx-auto">
        <div class="grid lg:grid-cols-2 gap-8">
            <!-- QR Scanner Section -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg overflow-hidden">
                <div class="p-6">
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-4">
                        {{ __('attendances.scan_qr_code') }}
                    </h2>
                    <div id="video-container" class="aspect-video bg-black rounded-lg overflow-hidden mb-4">
                        <video id="qr-video" class="w-full h-full object-cover"></video>
                    </div>
                    <!-- Camera selection and controls (unchanged) -->
                </div>
            </div>

            <!-- Scanned Information Section -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg overflow-hidden">
                <div class="p-6">
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-4">
                        {{ __('attendances.scanned_information') }}
                    </h2>
                    @if ($memorizer)
                        <div class="mb-6 flex items-center gap-4">
                            <div>
                                <h3 class="text-xl font-semibold text-gray-900 dark:text-white">
                                    {{ $memorizer->name }}
                                </h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ __('memorizers.memorizer_id') }}: {{ $memorizer->id }}
                                </p>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
                            @php
                                $infoItems = [
                                    'phone' => $memorizer->phone,
                                    'group' => $memorizer->memoGroup->name,
                                    'city' => $memorizer->city,
                                    'sex' => $memorizer->sex === 'male' ? __('app.male') : __('app.female'),
                                ];
                            @endphp
                            @foreach ($infoItems as $key => $value)
                                <div class="bg-gray-100 dark:bg-gray-700 p-4 rounded-lg">
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                                        {{ __("memorizers.$key") }}
                                    </p>
                                    <p class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">
                                        {{ $value }}
                                    </p>
                                </div>
                            @endforeach
                        </div>
                        @if (!$autoRegister)
                            <button wire:click="processScannedData"
                                class="w-full bg-primary-500 hover:bg-primary-600 text-white font-bold py-3 px-4 rounded-xl transition duration-150 ease-in-out">
                                {{ __('attendances.process_scanned_data') }}
                            </button>
                        @endif
                    @else
                        <div class="text-center py-8">
                            <p class="text-xl text-gray-500 dark:text-gray-400">
                                {{ __('attendances.no_memorizer_scanned') }}
                            </p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        @if ($message)
            <div
                class="mt-8 bg-green-100 dark:bg-green-800 border-l-4 border-green-500 text-green-700 dark:text-green-200 p-4 rounded-2xl">
                <p class="font-medium">{{ $message }}</p>
            </div>
        @endif

        @if ($wait)
            <div
                class="mt-8 bg-orange-100 dark:bg-orange-800 border-l-4 border-orange-500 text-orange-700 dark:text-orange-200 p-4 rounded-2xl flex items-center justify-center space-x-4">
                <p class="font-medium">{{ $wait }}</p>
                <x-filament::loading-indicator class="h-6 w-6" />
            </div>
        @endif
    </div>
</div>

@push('scripts')
    @vite(['resources/js/qr-scanner.js'])
    <script>
        document.addEventListener('livewire:init', () => {
            let cooldownTimer;

            Livewire.on('startCooldownTimer', ({
                cooldownSeconds
            }) => {
                clearTimeout(cooldownTimer);
                cooldownTimer = setTimeout(() => {
                    Livewire.dispatch('cooldownEnded');
                }, cooldownSeconds * 1000);
            });
        });
    </script>
@endpush
