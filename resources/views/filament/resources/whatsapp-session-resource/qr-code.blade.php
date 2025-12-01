<div class="space-y-4" @if (isset($status) && $status->canShowQrCode()) wire:poll.3s @endif>
    <div class="text-center">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
            امسح رمز QR لربط واتساب
        </h3>
        @if ($sessionName)
            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                الجلسة: {{ $sessionName }}
            </p>
        @endif
    </div>

    @if ($qrCode)
        <div class="flex justify-center">
            <div class="bg-white p-4 rounded-lg shadow-lg">
                @if (str_starts_with($qrCode, 'qr-content:'))
                    {{-- Client-side QR code generation --}}
                    <div id="qr-code-container" class="w-64 h-64 mx-auto flex items-center justify-center">
                        <div class="text-gray-500">جاري إنشاء رمز QR...</div>
                    </div>
                    <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
                    <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            const qrContent = {{ Js::from(substr($qrCode, 11)) }}; // Remove 'qr-content:' prefix
                            const container = document.getElementById('qr-code-container');

                            QRCode.toCanvas(qrContent, {
                                width: 256,
                                height: 256,
                                margin: 2,
                                color: {
                                    dark: '#000000',
                                    light: '#FFFFFF'
                                }
                            }).then(canvas => {
                                container.innerHTML = '';
                                container.appendChild(canvas);
                            }).catch(err => {
                                container.innerHTML = '<div class="text-red-500 text-sm">فشل في إنشاء رمز QR</div>';
                                console.error('QR code generation failed:', err);
                            });
                        });
                    </script>
                @else
                    <img src="{{ $qrCode }}" alt="رمز QR الخاص بواتساب"
                        class="w-64 h-64 mx-auto"
                        onerror="this.parentNode.innerHTML='<div class=\'w-64 h-64 flex items-center justify-center text-red-500\'>فشل في تحميل رمز QR</div>'" />
                @endif
            </div>
        </div>

        <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg">
            <div class="flex items-start">
                <div class="flex-shrink-0">
                    <svg class="w-5 h-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd"
                            d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                            clip-rule="evenodd"></path>
                    </svg>
                </div>
                <div class="ml-3">
                    <h4 class="text-sm font-medium text-blue-800 dark:text-blue-200">
                        التعليمات
                    </h4>
                    <div class="mt-2 text-sm text-blue-700 dark:text-blue-300">
                        <ol class="list-decimal list-inside space-y-1">
                            <li>افتح واتساب على هاتفك</li>
                            <li>اذهب إلى الإعدادات ← الأجهزة المرتبطة</li>
                            <li>اضغط على "ربط جهاز"</li>
                            <li>امسح رمز QR هذا</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <div class="text-center">
            <button type="button" wire:click="$refresh"
                class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" wire:loading.class="animate-spin">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15">
                    </path>
                </svg>
                <span wire:loading.remove>تحديث رمز QR</span>
                <span wire:loading>جاري التحديث...</span>
            </button>
        </div>
    @else
        <div class="text-center py-8">
            <svg class="w-12 h-12 mx-auto text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">
                رمز QR غير متوفر
            </h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                @if (isset($status))
                    @if ($status === \App\Enums\WhatsAppConnectionStatus::DISCONNECTED)
                        يرجى بدء الجلسة لإنشاء رمز QR
                    @elseif ($status->canShowQrCode())
                        يتم إنشاء رمز QR، يرجى الانتظار...
                    @else
                        الحالة الحالية: {{ $status->getLabel() }}
                    @endif
                @else
                    يرجى بدء الجلسة لإنشاء رمز QR
                @endif
            </p>
        </div>
    @endif
</div>