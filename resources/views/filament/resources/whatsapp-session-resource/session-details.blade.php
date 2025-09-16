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
            // Create confetti elements
            const confettiCount = 50;
            const colors = ['#10b981', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6'];
            const container = document.querySelector('.confetti-container');

            for (let i = 0; i < confettiCount; i++) {
                const confetti = document.createElement('div');
                confetti.className = 'confetti';
                confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
                confetti.style.left = Math.random() * 100 + '%';
                confetti.style.animationDelay = Math.random() * 3 + 's';
                confetti.style.animationDuration = (Math.random() * 3 + 2) + 's';
                container.appendChild(confetti);
            }

            // Remove confetti after animation
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
    class="relative w-full p-5 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl shadow-sm"
>
    <!-- Confetti Container -->
    <div class="confetti-container fixed inset-0 pointer-events-none z-50" x-show="showConfetti"></div>

    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div class="text-base font-semibold text-gray-900 dark:text-gray-100">
            {{ $getRecord()->name ?? 'جلسة واتساب' }}
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
            $badge = match ($status) {
                \App\Enums\WhatsAppConnectionStatus::CONNECTED
                    => 'bg-emerald-600/10 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-400/10 dark:text-emerald-300',
                \App\Enums\WhatsAppConnectionStatus::CREATING
                    => 'bg-blue-500/10 text-blue-700 ring-blue-600/20 dark:bg-blue-400/10 dark:text-blue-300',
                \App\Enums\WhatsAppConnectionStatus::CONNECTING
                    => 'bg-blue-500/10 text-blue-700 ring-blue-600/20 dark:bg-blue-400/10 dark:text-blue-300',
                \App\Enums\WhatsAppConnectionStatus::PENDING
                    => 'bg-amber-500/10 text-amber-700 ring-amber-600/20 dark:bg-amber-400/10 dark:text-amber-300',
                \App\Enums\WhatsAppConnectionStatus::GENERATING_QR
                    => 'bg-blue-500/10 text-blue-700 ring-blue-600/20 dark:bg-blue-400/10 dark:text-blue-300',
                \App\Enums\WhatsAppConnectionStatus::DISCONNECTED
                    => 'bg-rose-600/10 text-rose-700 ring-rose-600/20 dark:bg-rose-400/10 dark:text-rose-300',
                default => 'bg-gray-500/10 text-gray-700 ring-gray-600/20 dark:bg-gray-400/10 dark:text-gray-300',
            };
        @endphp
        <span class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-medium ring-1 ring-inset {{ $badge }}">
            @if($isLoading)
                <svg class="animate-spin h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            @endif
            {{ $statusLabel }}
        </span>
    </div>

    <!-- Connection Instructions -->
    @if($isLoading && $getRecord()->getQrCodeData())
        <div class="mt-4 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
            <div class="flex items-start gap-3">
                <svg class="h-5 w-5 text-blue-600 dark:text-blue-400 mt-0.5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="16" x2="12" y2="12"></line>
                    <line x1="12" y1="8" x2="12.01" y2="8"></line>
                </svg>
                <div class="flex-1">
                    <p class="text-sm font-medium text-blue-900 dark:text-blue-200">
                        جهز هاتفك
                    </p>
                    <p class="mt-1 text-xs text-blue-700 dark:text-blue-300">
                        يرجى الاحتفاظ بهاتفك بالقرب منك ومسح رمز QR أدناه. لا تغلق هذه الصفحة حتى ترى حالة "متصل".
                    </p>
                    <div class="mt-2 flex items-center gap-2">
                        <div class="flex gap-1">
                            <div class="w-2 h-2 bg-blue-500 rounded-full animate-bounce" style="animation-delay: 0ms;"></div>
                            <div class="w-2 h-2 bg-blue-500 rounded-full animate-bounce" style="animation-delay: 150ms;"></div>
                            <div class="w-2 h-2 bg-blue-500 rounded-full animate-bounce" style="animation-delay: 300ms;"></div>
                        </div>
                        <span class="text-xs text-blue-600 dark:text-blue-400">في انتظار الاتصال...</span>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Success Message -->
    @if($status === \App\Enums\WhatsAppConnectionStatus::CONNECTED)
        <div class="mt-4 p-4 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 rounded-lg">
            <div class="flex items-center gap-3">
                <svg class="h-5 w-5 text-emerald-600 dark:text-emerald-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
                <div>
                    <p class="text-sm font-medium text-emerald-900 dark:text-emerald-200">
                        تم الاتصال بنجاح!
                    </p>
                    <p class="mt-0.5 text-xs text-emerald-700 dark:text-emerald-300">
                        جلسة واتساب الخاصة بك نشطة وجاهزة لإرسال الرسائل.
                    </p>
                </div>
            </div>
        </div>
    @endif

    <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-3">
        <div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-4">
            <div class="text-xs text-gray-500 dark:text-gray-400">تاريخ الاتصال</div>
            <div class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                {{ $getRecord()->connected_at?->format('M d, Y H:i') ?? 'لم يتم الاتصال من قبل' }}
            </div>
        </div>
        <div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-4">
            <div class="text-xs text-gray-500 dark:text-gray-400">آخر نشاط</div>
            <div class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                {{ $getRecord()->last_activity_at?->diffForHumans() ?? 'لا يوجد نشاط' }}
            </div>
        </div>
        <div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-4">
            <div class="text-xs text-gray-500 dark:text-gray-400">تاريخ الإنشاء</div>
            <div class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                {{ $getRecord()->created_at->format('M d, Y H:i') }}
            </div>
        </div>
    </div>

    @php
        // Show QR code if it exists and status allows it
        $showQr = $getRecord()->getQrCodeData() && $getRecord()->status->shouldShowQrCode();
        $needsConnection = $getRecord()->status === \App\Enums\WhatsAppConnectionStatus::DISCONNECTED;
    @endphp

    <!-- Auto-start prompt for disconnected sessions -->
    @if($needsConnection)
        <div class="mt-5 rounded-xl border border-dashed border-amber-300 dark:border-amber-800 p-4 bg-amber-50/60 dark:bg-amber-900/20">
            <div class="flex items-center justify-between mb-3">
                <div class="text-sm font-medium text-amber-900 dark:text-amber-200">
                    الجلسة غير متصلة
                </div>
                <span class="text-xs text-amber-700 dark:text-amber-300">{{ $getRecord()->name }}</span>
            </div>
            <div class="text-center">
                <div class="text-6xl mb-4">📱</div>
                <p class="text-sm text-amber-800 dark:text-amber-200 mb-4">
                    انقر للحصول على رمز QR وربط واتساب
                </p>
                <button
                    wire:click="$dispatch('start-session', { sessionId: '{{ $getRecord()->id }}' })"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white text-sm font-medium rounded-lg transition-colors duration-200"
                >
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polygon points="5 3 19 12 5 21 5 3"></polygon>
                    </svg>
                    بدء الجلسة والحصول على رمز QR
                </button>
            </div>
        </div>
    @endif

    @if ($showQr)
        <div class="mt-5 rounded-xl border border-dashed border-sky-300 dark:border-sky-800 p-4 bg-sky-50/60 dark:bg-sky-900/20">
            <div class="flex items-center justify-between mb-3">
                <div class="text-sm font-medium text-sky-900 dark:text-sky-200">
                    امسح رمز QR باستخدام واتساب
                </div>
                <span class="text-xs text-sky-700 dark:text-sky-300">{{ $getRecord()->name }}</span>
            </div>
            <div class="flex flex-col items-center gap-4 md:flex-row">
                <div class="relative">
                    @php $qrData = $getRecord()->getQrCodeData(); @endphp
                    @if (str_starts_with($qrData, 'qr-content:'))
                        {{-- Client-side QR code generation --}}
                        <div id="session-qr-container-{{ $getRecord()->id }}" class="h-48 w-48 rounded-lg ring-1 ring-sky-300 dark:ring-sky-700 bg-white flex items-center justify-center">
                            <div class="text-gray-500 text-sm">جاري إنشاء رمز QR...</div>
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
                                        width: 192,
                                        height: 192,
                                        margin: 2,
                                        color: {
                                            dark: '#000000',
                                            light: '#FFFFFF'
                                        }
                                    }).then(canvas => {
                                        container.innerHTML = '';
                                        canvas.className = 'h-48 w-48 rounded-lg';
                                        container.appendChild(canvas);
                                    }).catch(err => {
                                        container.innerHTML = '<div class="text-red-500 text-xs">فشل في إنشاء رمز QR</div>';
                                        console.error('QR code generation failed:', err);
                                    });
                                }
                            });
                        </script>
                    @else
                        <img src="{{ $qrData }}" alt="QR"
                            class="h-48 w-48 rounded-lg ring-1 ring-sky-300 dark:ring-sky-700 bg-white" />
                    @endif
                    @if($isLoading)
                        <div class="absolute inset-0 flex items-center justify-center rounded-lg bg-white/20 backdrop-blur-[1px]">
                            <div class="w-full h-full rounded-lg border-2 border-sky-500 animate-pulse"></div>
                        </div>
                    @endif
                </div>
                <div class="flex-1">
                    <div class="space-y-3">
                        <div class="flex items-start gap-2">
                            <span class="text-lg">📱</span>
                            <div>
                                <p class="text-sm font-medium text-gray-900 dark:text-gray-100">الخطوة 1</p>
                                <p class="text-xs text-gray-600 dark:text-gray-400">افتح واتساب على هاتفك</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-2">
                            <span class="text-lg">⚙️</span>
                            <div>
                                <p class="text-sm font-medium text-gray-900 dark:text-gray-100">الخطوة 2</p>
                                <p class="text-xs text-gray-600 dark:text-gray-400">اذهب إلى الإعدادات > الأجهزة المرتبطة</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-2">
                            <span class="text-lg">📷</span>
                            <div>
                                <p class="text-sm font-medium text-gray-900 dark:text-gray-100">الخطوة 3</p>
                                <p class="text-xs text-gray-600 dark:text-gray-400">اضغط على "ربط جهاز" وامسح رمز QR هذا</p>
                            </div>
                        </div>
                    </div>
                    @if($isLoading)
                        <div class="mt-4 p-3 bg-amber-50 dark:bg-amber-900/20 rounded-lg border border-amber-200 dark:border-amber-800">
                            <div class="flex items-center gap-2">
                                <svg class="h-4 w-4 text-amber-600 dark:text-amber-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <line x1="12" y1="8" x2="12" y2="12"></line>
                                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                                </svg>
                                <p class="text-xs text-amber-800 dark:text-amber-200">
                                    احتفظ بهذه الصفحة مفتوحة حتى الاتصال
                                </p>
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
            transform: translateY(100vh) rotate(720deg);
            opacity: 0;
        }
    }

    .confetti {
        position: fixed;
        width: 10px;
        height: 10px;
        animation: confetti-fall linear forwards;
    }
</style>