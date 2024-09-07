<div class="p-4 sm:p-6 lg:p-8">
    <div class="max-w-full mx-auto grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="space-y-6">
            <h2 class="text-2xl font-bold text-primary-800 dark:text-primary-100">مسح رمز QR</h2>

            @if ($cameraAvailable)
                <div id="video-container"
                    class="aspect-video w-full rounded-lg overflow-hidden shadow-lg bg-white dark:bg-gray-800">
                    <video id="qr-video" class="w-full h-full object-cover"></video>
                </div>

                <div class="flex gap-3">
                    <select id="cam-list"
                        class="flex-1 bg-white dark:bg-gray-700 text-primary-800 dark:text-primary-100 border border-primary-300 dark:border-primary-600 rounded-md shadow-sm p-2 text-sm">
                        <option value="environment">الكاميرا الخلفية</option>
                        <option value="user">الكاميرا الأمامية</option>
                    </select>
                    <select id="inversion-mode-select"
                        class="flex-1 bg-white dark:bg-gray-700 text-primary-800 dark:text-primary-100 border border-primary-300 dark:border-primary-600 rounded-md shadow-sm p-2 text-sm">
                        <option value="original">المسح الأصلي</option>
                        <option value="invert">المسح المعكوس</option>
                        <option value="both" selected>كلاهما</option>
                    </select>
                </div>

                <div class="flex gap-2">
                    <button id="start-button"
                        class="flex-1 bg-green-500 hover:bg-green-600 dark:bg-green-600 dark:hover:bg-green-700 text-white px-4 py-2 rounded-md text-sm transition duration-300 ease-in-out">
                        بدء
                    </button>
                    <button id="stop-button"
                        class="flex-1 bg-red-500 hover:bg-red-600 dark:bg-red-600 dark:hover:bg-red-700 text-white px-4 py-2 rounded-md text-sm transition duration-300 ease-in-out">
                        إيقاف
                    </button>
                </div>
            @else
                <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4" role="alert">
                    <p class="font-bold">تنبيه</p>
                    <p>لم يتم العثور على كاميرا متصلة بهذا الجهاز. يرجى التأكد من وجود كاميرا متصلة والمحاولة مرة أخرى.
                    </p>
                </div>
                <button wire:click="$refresh"
                    class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded">
                    إعادة المحاولة
                </button>
            @endif
        </div>

        <div class="space-y-6">
            @if ($memorizer)
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden">
                    <div class="bg-primary-100 dark:bg-primary-800 p-4">
                        <h3 class="text-lg font-semibold text-primary-800 dark:text-primary-100">
                            معلومات الحافظ
                        </h3>
                    </div>
                    <div class="p-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="space-y-3">
                                <div>
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">الاسم</p>
                                    <p class="text-base font-semibold text-gray-900 dark:text-white">
                                        {{ $memorizer->name }}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">رقم الهاتف</p>
                                    <p class="text-base font-semibold text-gray-900 dark:text-white">
                                        {{ $memorizer->phone }}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">المجموعة</p>
                                    <p class="text-base font-semibold text-gray-900 dark:text-white">
                                        {{ $memorizer->memoGroup->name }}
                                    </p>
                                </div>
                            </div>
                            <div class="space-y-3">
                                <div>
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">المدينة</p>
                                    <p class="text-base font-semibold text-gray-900 dark:text-white">
                                        {{ $memorizer->city }}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">الجنس</p>
                                    <p class="text-base font-semibold text-gray-900 dark:text-white">
                                        {{ $memorizer->sex === 'male' ? 'ذكر' : 'أنثى' }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3">
                        <button wire:click="processScannedData"
                            class="w-full bg-primary-600 hover:bg-primary-700 dark:bg-primary-500 dark:hover:bg-primary-600 text-white px-4 py-2 rounded-md text-sm transition duration-300 ease-in-out">
                            تسجيل الحضور
                        </button>
                    </div>
                </div>
            @else
                <div class="bg-gray-100 dark:bg-gray-700 p-4 rounded-lg text-center text-gray-600 dark:text-gray-300">
                    لم يتم مسح أي رمز QR بعد
                </div>
            @endif

            @if ($message)
                <div
                    class="bg-green-100 dark:bg-green-800 border-l-4 border-green-500 text-green-700 dark:text-green-200 p-3 rounded-md text-sm">
                    <p class="font-medium">{{ $message }}</p>
                </div>
            @endif
        </div>
    </div>
</div>

@push('scripts')
    @vite(['resources/js/qr-scanner.js'])
    <script>
        document.addEventListener('livewire:init', () => {
            Livewire.on('qr-scanner-mounted', () => {
                console.log('QR Scanner component mounted');
                window.qrScanner.init();
            });
        });
    </script>
@endpush
