<div
    x-data="{
        wasConnected: @js($getRecord()->status === \App\Enums\WhatsAppConnectionStatus::CONNECTED),
        currentStatus: @js($getRecord()->status->value),
        showConfetti: false,
        checkStatusChange() {
            const newStatus = @js($getRecord()->status->value);
            if (!this.wasConnected && newStatus === 'connected') {
                this.wasConnected = true;
                this.showConfetti = true;
                this.triggerConfetti();
            }
            this.currentStatus = newStatus;
        },
        triggerConfetti() {
            const confettiCount = 50;
            const colors = ['#10b981', '#3b82f6', '#f59e0b'];
            const container = document.querySelector('.confetti-container');

            for (let i = 0; i < confettiCount; i++) {
                const confetti = document.createElement('div');
                confetti.className = 'confetti';
                confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
                confetti.style.left = Math.random() * 100 + '%';
                confetti.style.animationDelay = Math.random() * 2 + 's';
                confetti.style.animationDuration = (Math.random() * 2 + 3) + 's';
                container.appendChild(confetti);
            }

            setTimeout(() => {
                container.innerHTML = '';
                this.showConfetti = false;
            }, 5000);
        }
    }"
    @if ($getRecord()->status->shouldPoll())
        wire:poll.3s="pollStatus('{{ $getRecord()->id }}')"
        x-effect="checkStatusChange()"
    @endif
    class="relative w-full overflow-hidden bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl shadow-sm transition-all duration-300"
>
    <!-- Confetti Container -->
    <div class="confetti-container fixed inset-0 pointer-events-none z-50" x-show="showConfetti"></div>

    <div class="p-4">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 bg-green-500 rounded-lg flex items-center justify-center">
                    <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893A11.821 11.821 0 0020.885 3.785"/>
                    </svg>
                </div>
                <h3 class="font-semibold text-gray-900 dark:text-gray-100">
                    {{ $getRecord()->name ?? 'جلسة واتساب' }}
                </h3>
            </div>

            @php
                $status = $getRecord()->status;
                $statusLabel = $status->getLabel();
                $isLoading = in_array($status, [
                    \App\Enums\WhatsAppConnectionStatus::CREATING,
                    \App\Enums\WhatsAppConnectionStatus::CONNECTING,
                    \App\Enums\WhatsAppConnectionStatus::PENDING,
                    \App\Enums\WhatsAppConnectionStatus::GENERATING_QR,
                ]);
                $badgeColor = match ($status) {
                    \App\Enums\WhatsAppConnectionStatus::CONNECTED => 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100',
                    \App\Enums\WhatsAppConnectionStatus::DISCONNECTED => 'bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100',
                    default => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-100',
                };
                $statusIcon = match ($status) {
                    \App\Enums\WhatsAppConnectionStatus::CONNECTED => '✅',
                    \App\Enums\WhatsAppConnectionStatus::DISCONNECTED => '❌',
                    default => '⏳',
                };
            @endphp

            <span class="inline-flex items-center gap-1 px-3 py-1 text-sm font-medium rounded-full {{ $badgeColor }}">
                @if($isLoading)
                    <svg class="animate-spin h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                @else
                    {{ $statusIcon }}
                @endif
                {{ $statusLabel }}
            </span>
        </div>
    </div>

    <!-- Success Message -->
    @if($status === \App\Enums\WhatsAppConnectionStatus::CONNECTED)
        <div class="mx-4 mb-4 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-700 rounded-lg">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 bg-green-500 rounded-lg flex items-center justify-center">
                    <svg class="h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                </div>
                <div>
                    <h4 class="font-semibold text-green-900 dark:text-green-100">
                        تم الاتصال بنجاح!
                    </h4>
                    <p class="text-sm text-green-700 dark:text-green-300">
                        الجلسة نشطة وجاهزة لإرسال الرسائل
                    </p>
                </div>
            </div>
        </div>
    @endif

    @php
        // Show QR code if it exists and status allows it
        $showQr = $getRecord()->getQrCodeData() && $getRecord()->status->shouldShowQrCode();
        $needsConnection = $getRecord()->status === \App\Enums\WhatsAppConnectionStatus::DISCONNECTED;
    @endphp

    <!-- Auto-start prompt for disconnected sessions -->
    @if($needsConnection)
        <div class="mx-4 mb-4 p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-600 rounded-lg text-center">
            <h4 class="font-semibold text-amber-900 dark:text-amber-100 mb-2">
                الجلسة غير متصلة
            </h4>
            <p class="text-sm text-amber-800 dark:text-amber-200 mb-4">
                انقر على "بدء الجلسة" للحصول على رمز QR
            </p>
        </div>
    @endif

    @if ($showQr)
        <div class="mx-4 mb-4 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-600 rounded-lg">
            <div class="text-center mb-4">
                <h4 class="font-semibold text-blue-900 dark:text-blue-100 mb-1">
                    📱 امسح رمز QR باستخدام واتساب
                </h4>
                <p class="text-sm text-blue-700 dark:text-blue-300">
                    استخدم تطبيق واتساب لمسح الرمز أدناه
                </p>
            </div>

            <div class="flex flex-col lg:flex-row gap-8 items-center">
                <!-- QR Code Section -->
                <div class="flex-shrink-0">
                    <div class="relative">
                        @php $qrData = $getRecord()->getQrCodeData(); @endphp
                        @if (str_starts_with($qrData, 'qr-content:'))
                            {{-- Client-side QR code generation --}}
                            <div id="session-qr-container-{{ $getRecord()->id }}" class="h-56 w-56 rounded-2xl ring-2 ring-sky-300 dark:ring-sky-600 bg-white shadow-xl flex items-center justify-center relative overflow-hidden">
                                <div class="text-gray-500 text-sm font-medium">🔄 جاري إنشاء رمز QR...</div>
                                <div class="absolute inset-0 bg-gradient-to-br from-sky-400/10 to-blue-400/10"></div>
                            </div>
                            @once
                                <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
                            @endonce
                            <script>
                                document.addEventListener('DOMContentLoaded', function() {
                                    const qrContent = {{ Js::from(substr($qrData, 11)) }}; // Remove 'qr-content:' prefix
                                    const container = document.getElementById('session-qr-container-{{ $getRecord()->id }}');

                                    if (container && window.QRCode) {
                                        QRCode.toCanvas(qrContent, {
                                            width: 224,
                                            height: 224,
                                            margin: 2,
                                            color: {
                                                dark: '#1e293b',
                                                light: '#ffffff'
                                            }
                                        }).then(canvas => {
                                            container.innerHTML = '';
                                            canvas.className = 'h-56 w-56 rounded-2xl shadow-xl';
                                            container.appendChild(canvas);
                                        }).catch(err => {
                                            container.innerHTML = '<div class="text-red-500 text-sm font-medium">⚠️ فشل في إنشاء رمز QR</div>';
                                            console.error('QR code generation failed:', err);
                                        });
                                    }
                                });
                            </script>
                        @else
                            <img src="{{ $qrData }}" alt="QR Code"
                                class="h-56 w-56 rounded-2xl ring-2 ring-sky-300 dark:ring-sky-600 bg-white shadow-xl" />
                        @endif
                        @if($isLoading)
                            <div class="absolute inset-0 flex items-center justify-center rounded-2xl bg-white/30 backdrop-blur-sm">
                                <div class="w-full h-full rounded-2xl border-4 border-sky-500 animate-pulse shadow-lg"></div>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Instructions Section -->
                <div class="flex-1 max-w-md">
                    <div class="space-y-4">
                        <div class="flex items-start gap-4 p-4 bg-white/60 dark:bg-gray-800/60 rounded-xl backdrop-blur-sm">
                            <div class="flex-shrink-0 w-8 h-8 bg-gradient-to-br from-green-400 to-green-600 rounded-lg flex items-center justify-center shadow-md">
                                <span class="text-white text-lg font-bold">1</span>
                            </div>
                            <div>
                                <p class="text-sm font-bold text-gray-900 dark:text-gray-100 mb-1">📱 افتح واتساب</p>
                                <p class="text-xs text-gray-600 dark:text-gray-400 leading-relaxed">افتح تطبيق واتساب على هاتفك الذكي</p>
                            </div>
                        </div>

                        <div class="flex items-start gap-4 p-4 bg-white/60 dark:bg-gray-800/60 rounded-xl backdrop-blur-sm">
                            <div class="flex-shrink-0 w-8 h-8 bg-gradient-to-br from-blue-400 to-blue-600 rounded-lg flex items-center justify-center shadow-md">
                                <span class="text-white text-lg font-bold">2</span>
                            </div>
                            <div>
                                <p class="text-sm font-bold text-gray-900 dark:text-gray-100 mb-1">⚙️ اذهب للإعدادات</p>
                                <p class="text-xs text-gray-600 dark:text-gray-400 leading-relaxed">الإعدادات ← الأجهزة المرتبطة</p>
                            </div>
                        </div>

                        <div class="flex items-start gap-4 p-4 bg-white/60 dark:bg-gray-800/60 rounded-xl backdrop-blur-sm">
                            <div class="flex-shrink-0 w-8 h-8 bg-gradient-to-br from-purple-400 to-purple-600 rounded-lg flex items-center justify-center shadow-md">
                                <span class="text-white text-lg font-bold">3</span>
                            </div>
                            <div>
                                <p class="text-sm font-bold text-gray-900 dark:text-gray-100 mb-1">📷 امسح الرمز</p>
                                <p class="text-xs text-gray-600 dark:text-gray-400 leading-relaxed">اضغط "ربط جهاز" وامسح رمز QR</p>
                            </div>
                        </div>
                    </div>

                    @if($isLoading)
                        <div class="mt-6 p-4 bg-gradient-to-r from-amber-50 to-orange-50 dark:from-amber-900/30 dark:to-orange-900/30 rounded-xl border border-amber-200 dark:border-amber-700 backdrop-blur-sm">
                            <div class="flex items-center gap-3">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-amber-600 dark:text-amber-400 animate-spin" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M21 12a9 9 0 11-6.219-8.56"/>
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-amber-800 dark:text-amber-200">
                                        🔄 في انتظار المسح...
                                    </p>
                                    <p class="text-xs text-amber-600 dark:text-amber-400 mt-1">
                                        احتفظ بهذه الصفحة مفتوحة حتى اكتمال الاتصال
                                    </p>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>

<style>
    @keyframes confetti-fall {
        0% {
            transform: translateY(-100vh) rotate(0deg);
            opacity: 1;
        }
        100% {
            transform: translateY(100vh) rotate(360deg);
            opacity: 0;
        }
    }

    .confetti {
        position: fixed;
        width: 8px;
        height: 8px;
        animation: confetti-fall 3s linear forwards;
        border-radius: 2px;
        z-index: 9999;
    }
</style>