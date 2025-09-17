<div
    x-data="{
        wasConnected: @js($getRecord()->status === \App\Enums\WhatsAppConnectionStatus::CONNECTED),
        currentStatus: @js($getRecord()->status->value),
        showConfetti: false,
        pollCount: 0,
        lastPolled: Date.now(),
        pollInterval: 2000, // Start with 2 seconds
        maxPollInterval: 30000, // Max 30 seconds
        consecutiveStablePollsCount: 0,
        init() {
            this.setupAdaptivePolling();
        },
        checkStatusChange() {
            const newStatus = @js($getRecord()->status->value);
            const statusChanged = this.currentStatus !== newStatus;

            if (!this.wasConnected && newStatus === 'connected') {
                this.wasConnected = true;
                this.showConfetti = true;
                this.triggerConfetti();
                this.resetPollInterval(); // Reset to fast polling on connection
            }

            if (statusChanged) {
                this.consecutiveStablePollsCount = 0;
                this.resetPollInterval();
            } else {
                this.consecutiveStablePollsCount++;
                this.adjustPollInterval();
            }

            this.currentStatus = newStatus;
            this.lastPolled = Date.now();
            this.pollCount++;
        },
        setupAdaptivePolling() {
            // Set up dynamic polling interval based on status
            const status = @js($getRecord()->status->value);
            if (status === 'connected' || status === 'disconnected') {
                this.pollInterval = 10000; // 10 seconds for stable states
            } else {
                this.pollInterval = 2000; // 2 seconds for transitional states
            }
        },
        adjustPollInterval() {
            // Gradually increase polling interval for stable status
            if (this.consecutiveStablePollsCount > 5) {
                this.pollInterval = Math.min(this.pollInterval * 1.5, this.maxPollInterval);
            }
        },
        resetPollInterval() {
            this.pollInterval = 2000;
            this.consecutiveStablePollsCount = 0;
        },
        triggerConfetti() {
            const confettiCount = 80;
            const colors = ['#10b981', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6', '#f97316', '#06b6d4', '#84cc16'];
            const container = document.querySelector('.confetti-container');

            for (let i = 0; i < confettiCount; i++) {
                const confetti = document.createElement('div');
                confetti.className = 'confetti';
                confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
                confetti.style.left = Math.random() * 100 + '%';
                confetti.style.animationDelay = Math.random() * 2 + 's';
                confetti.style.animationDuration = (Math.random() * 2 + 3) + 's';

                // Add some variety to confetti shapes
                if (Math.random() > 0.5) {
                    confetti.style.borderRadius = '50%';
                }

                container.appendChild(confetti);
            }

            // Add celebration sound effect simulation
            if (navigator.vibrate) {
                navigator.vibrate([100, 50, 100]);
            }

            setTimeout(() => {
                container.innerHTML = '';
                this.showConfetti = false;
            }, 7000);
        },
        getTimeSinceLastPoll() {
            return Math.floor((Date.now() - this.lastPolled) / 1000);
        },
        getPollIntervalDisplay() {
            return Math.floor(this.pollInterval / 1000) + 's';
        },
        getConnectionHealth() {
            const timeSince = this.getTimeSinceLastPoll();
            if (timeSince < 5) return { status: 'excellent', color: 'green', label: 'ممتاز' };
            if (timeSince < 15) return { status: 'good', color: 'blue', label: 'جيد' };
            if (timeSince < 30) return { status: 'fair', color: 'yellow', label: 'متوسط' };
            return { status: 'poor', color: 'red', label: 'ضعيف' };
        }
    }"
    @if ($getRecord()->status->shouldPoll())
        wire:poll.2s="pollStatus('{{ $getRecord()->id }}')"
        x-effect="checkStatusChange()"
    @endif
    class="relative w-full overflow-hidden bg-gradient-to-br from-white to-gray-50 dark:from-gray-900 dark:to-gray-950 border border-gray-200 dark:border-gray-800 rounded-2xl shadow-lg hover:shadow-xl transition-all duration-300"
>
    <!-- Confetti Container -->
    <div class="confetti-container fixed inset-0 pointer-events-none z-50" x-show="showConfetti"></div>

    <div class="p-6">
        <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
            <div class="flex-1">
                <div class="flex items-center gap-3 mb-2">
                    <div class="flex-shrink-0">
                        <div class="w-10 h-10 bg-gradient-to-br from-green-400 to-green-600 rounded-xl flex items-center justify-center shadow-lg">
                            <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893A11.821 11.821 0 0020.885 3.785"/>
                            </svg>
                        </div>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">
                            {{ $getRecord()->name ?? 'جلسة واتساب' }}
                        </h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            رقم الجلسة: {{ $getRecord()->id }}
                        </p>
                    </div>
                </div>
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
                        => 'bg-emerald-500/15 text-emerald-700 ring-emerald-500/30 dark:bg-emerald-400/15 dark:text-emerald-300 shadow-emerald-500/20',
                    \App\Enums\WhatsAppConnectionStatus::CREATING
                        => 'bg-blue-500/15 text-blue-700 ring-blue-500/30 dark:bg-blue-400/15 dark:text-blue-300 shadow-blue-500/20',
                    \App\Enums\WhatsAppConnectionStatus::CONNECTING
                        => 'bg-blue-500/15 text-blue-700 ring-blue-500/30 dark:bg-blue-400/15 dark:text-blue-300 shadow-blue-500/20',
                    \App\Enums\WhatsAppConnectionStatus::PENDING
                        => 'bg-amber-500/15 text-amber-700 ring-amber-500/30 dark:bg-amber-400/15 dark:text-amber-300 shadow-amber-500/20',
                    \App\Enums\WhatsAppConnectionStatus::GENERATING_QR
                        => 'bg-purple-500/15 text-purple-700 ring-purple-500/30 dark:bg-purple-400/15 dark:text-purple-300 shadow-purple-500/20',
                    \App\Enums\WhatsAppConnectionStatus::DISCONNECTED
                        => 'bg-rose-500/15 text-rose-700 ring-rose-500/30 dark:bg-rose-400/15 dark:text-rose-300 shadow-rose-500/20',
                    default => 'bg-gray-500/15 text-gray-700 ring-gray-500/30 dark:bg-gray-400/15 dark:text-gray-300 shadow-gray-500/20',
                };
                $statusIcon = match ($status) {
                    \App\Enums\WhatsAppConnectionStatus::CONNECTED => '✅',
                    \App\Enums\WhatsAppConnectionStatus::CREATING => '🔄',
                    \App\Enums\WhatsAppConnectionStatus::CONNECTING => '📡',
                    \App\Enums\WhatsAppConnectionStatus::PENDING => '⏳',
                    \App\Enums\WhatsAppConnectionStatus::GENERATING_QR => '📱',
                    \App\Enums\WhatsAppConnectionStatus::DISCONNECTED => '❌',
                    default => '❓',
                };
            @endphp

            <div class="flex flex-col gap-2 items-end">
                <span class="inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-semibold ring-2 ring-inset shadow-lg {{ $badge }} transition-all duration-300">
                    @if($isLoading)
                        <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    @else
                        <span class="text-lg">{{ $statusIcon }}</span>
                    @endif
                    {{ $statusLabel }}
                </span>

                @if($status->shouldPoll())
                    <div class="flex flex-col gap-1 text-xs text-gray-500 dark:text-gray-400">
                        <div class="flex items-center gap-1">
                            <div class="w-1.5 h-1.5 bg-green-500 rounded-full animate-pulse"></div>
                            <span>مراقب نشط</span>
                            <span x-text="'(' + pollCount + ' فحص)'"></span>
                        </div>
                        <div class="flex items-center gap-2 text-xs">
                            <span>معدل الفحص:</span>
                            <span x-text="getPollIntervalDisplay()" class="font-mono bg-gray-100 dark:bg-gray-800 px-1 rounded"></span>
                            <span>|</span>
                            <span>الجودة:</span>
                            <span x-text="getConnectionHealth().label" :class="'text-' + getConnectionHealth().color + '-600'"></span>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Connection Instructions -->
    @if($isLoading && $getRecord()->getQrCodeData())
        <div class="mx-6 mb-4 p-5 bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900/30 dark:to-indigo-900/30 border border-blue-200 dark:border-blue-700 rounded-2xl backdrop-blur-sm">
            <div class="flex items-start gap-4">
                <div class="flex-shrink-0">
                    <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-xl flex items-center justify-center shadow-lg">
                        <svg class="h-6 w-6 text-white" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="16" x2="12" y2="12"></line>
                            <line x1="12" y1="8" x2="12.01" y2="8"></line>
                        </svg>
                    </div>
                </div>
                <div class="flex-1">
                    <h4 class="text-base font-bold text-blue-900 dark:text-blue-100 mb-2">
                        🚀 جهز هاتفك للاتصال
                    </h4>
                    <p class="text-sm text-blue-700 dark:text-blue-300 mb-4 leading-relaxed">
                        يرجى الاحتفاظ بهاتفك بالقرب منك ومسح رمز QR أدناه. <strong>لا تغلق هذه الصفحة</strong> حتى ترى حالة "متصل".
                    </p>
                    <div class="flex items-center gap-3 p-3 bg-white/50 dark:bg-gray-800/50 rounded-xl">
                        <div class="flex gap-1">
                            <div class="w-2.5 h-2.5 bg-blue-500 rounded-full animate-bounce" style="animation-delay: 0ms;"></div>
                            <div class="w-2.5 h-2.5 bg-blue-500 rounded-full animate-bounce" style="animation-delay: 150ms;"></div>
                            <div class="w-2.5 h-2.5 bg-blue-500 rounded-full animate-bounce" style="animation-delay: 300ms;"></div>
                        </div>
                        <span class="text-sm font-medium text-blue-600 dark:text-blue-400">في انتظار الاتصال...</span>
                        <div class="ml-auto flex flex-col items-end text-xs text-blue-500 dark:text-blue-400">
                            <span x-text="'فحص رقم: ' + pollCount"></span>
                            <span x-text="'كل ' + getPollIntervalDisplay()" class="font-mono bg-blue-100 dark:bg-blue-900 px-1 rounded mt-1"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Success Message -->
    @if($status === \App\Enums\WhatsAppConnectionStatus::CONNECTED)
        <div class="mx-6 mb-4 p-6 bg-gradient-to-r from-emerald-50 to-green-50 dark:from-emerald-900/30 dark:to-green-900/30 border border-emerald-200 dark:border-emerald-700 rounded-2xl backdrop-blur-sm shadow-lg">
            <div class="flex items-center gap-4">
                <div class="flex-shrink-0">
                    <div class="w-12 h-12 bg-gradient-to-br from-emerald-500 to-green-600 rounded-xl flex items-center justify-center shadow-lg animate-pulse">
                        <svg class="h-6 w-6 text-white" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                            <polyline points="22 4 12 14.01 9 11.01"></polyline>
                        </svg>
                    </div>
                </div>
                <div class="flex-1">
                    <h4 class="text-lg font-bold text-emerald-900 dark:text-emerald-100 mb-1">
                        🎉 تم الاتصال بنجاح!
                    </h4>
                    <p class="text-sm text-emerald-700 dark:text-emerald-300 leading-relaxed">
                        جلسة واتساب الخاصة بك <strong>نشطة وجاهزة</strong> لإرسال الرسائل. يمكنك الآن البدء في التواصل مع الطلاب والأولياء.
                    </p>
                    <div class="mt-3 flex items-center gap-2 text-xs text-emerald-600 dark:text-emerald-400">
                        <div class="w-2 h-2 bg-emerald-500 rounded-full animate-pulse"></div>
                        <span>الجلسة متصلة ونشطة</span>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="px-6 pb-4">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div class="group relative overflow-hidden rounded-xl bg-gradient-to-br from-blue-50 to-blue-100 dark:from-blue-900/20 dark:to-blue-800/20 p-4 transition-all duration-300 hover:shadow-lg hover:scale-105">
                <div class="flex items-center gap-3">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-blue-500 rounded-lg flex items-center justify-center">
                            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        </div>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-xs font-medium text-blue-600 dark:text-blue-400 uppercase tracking-wide">تاريخ الاتصال</div>
                        <div class="mt-1 text-sm font-semibold text-gray-900 dark:text-gray-100 truncate">
                            {{ $getRecord()->connected_at?->format('M d, Y H:i') ?? 'لم يتم الاتصال من قبل' }}
                        </div>
                    </div>
                </div>
            </div>

            <div class="group relative overflow-hidden rounded-xl bg-gradient-to-br from-green-50 to-green-100 dark:from-green-900/20 dark:to-green-800/20 p-4 transition-all duration-300 hover:shadow-lg hover:scale-105">
                <div class="flex items-center gap-3">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-green-500 rounded-lg flex items-center justify-center">
                            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                        </div>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-xs font-medium text-green-600 dark:text-green-400 uppercase tracking-wide">آخر نشاط</div>
                        <div class="mt-1 text-sm font-semibold text-gray-900 dark:text-gray-100 truncate">
                            {{ $getRecord()->last_activity_at?->diffForHumans() ?? 'لا يوجد نشاط' }}
                        </div>
                    </div>
                </div>
            </div>

            <div class="group relative overflow-hidden rounded-xl bg-gradient-to-br from-purple-50 to-purple-100 dark:from-purple-900/20 dark:to-purple-800/20 p-4 transition-all duration-300 hover:shadow-lg hover:scale-105">
                <div class="flex items-center gap-3">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-purple-500 rounded-lg flex items-center justify-center">
                            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                            </svg>
                        </div>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-xs font-medium text-purple-600 dark:text-purple-400 uppercase tracking-wide">تاريخ الإنشاء</div>
                        <div class="mt-1 text-sm font-semibold text-gray-900 dark:text-gray-100 truncate">
                            {{ $getRecord()->created_at->format('M d, Y H:i') }}
                        </div>
                    </div>
                </div>
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
        <div class="mx-6 mb-4 rounded-2xl border-2 border-dashed border-amber-300 dark:border-amber-600 p-6 bg-gradient-to-br from-amber-50/80 to-orange-50/80 dark:from-amber-900/20 dark:to-orange-900/20 backdrop-blur-sm transition-all duration-300 hover:shadow-lg">
            <div class="text-center">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-br from-amber-400 to-orange-500 rounded-2xl shadow-lg mb-4 animate-bounce">
                    <span class="text-2xl">📱</span>
                </div>
                <h4 class="text-lg font-bold text-amber-900 dark:text-amber-100 mb-2">
                    الجلسة غير متصلة
                </h4>
                <p class="text-sm text-amber-800 dark:text-amber-200 mb-6 max-w-md mx-auto leading-relaxed">
                    لبدء استخدام واتساب، انقر على الزر أدناه للحصول على رمز QR وربط هاتفك بالنظام.
                </p>
                <button
                    wire:click="$dispatch('start-session', { sessionId: '{{ $getRecord()->id }}' })"
                    class="inline-flex items-center gap-3 px-6 py-3 bg-gradient-to-r from-amber-600 to-orange-600 hover:from-amber-700 hover:to-orange-700 text-white text-sm font-bold rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 transform hover:scale-105"
                >
                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polygon points="5 3 19 12 5 21 5 3"></polygon>
                    </svg>
                    🚀 بدء الجلسة والحصول على رمز QR
                </button>
                <p class="mt-3 text-xs text-amber-600 dark:text-amber-400">
                    ستحتاج إلى هاتفك لمسح رمز QR
                </p>
            </div>
        </div>
    @endif

    @if ($showQr)
        <div class="mx-6 mb-6 rounded-2xl border-2 border-dashed border-sky-300 dark:border-sky-600 p-6 bg-gradient-to-br from-sky-50/80 to-blue-50/80 dark:from-sky-900/20 dark:to-blue-900/20 backdrop-blur-sm shadow-lg">
            <div class="text-center mb-6">
                <div class="inline-flex items-center justify-center w-12 h-12 bg-gradient-to-br from-sky-500 to-blue-600 rounded-xl shadow-lg mb-3">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h4"/>
                    </svg>
                </div>
                <h4 class="text-lg font-bold text-sky-900 dark:text-sky-100 mb-1">
                    📱 امسح رمز QR باستخدام واتساب
                </h4>
                <p class="text-sm text-sky-700 dark:text-sky-300">
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
            transform: translateY(-100vh) rotate(0deg) scale(1);
            opacity: 1;
        }
        50% {
            opacity: 1;
            transform: translateY(50vh) rotate(360deg) scale(1.2);
        }
        100% {
            transform: translateY(100vh) rotate(720deg) scale(0.8);
            opacity: 0;
        }
    }

    @keyframes pulse-glow {
        0%, 100% {
            box-shadow: 0 0 20px rgba(16, 185, 129, 0.4);
        }
        50% {
            box-shadow: 0 0 30px rgba(16, 185, 129, 0.8);
        }
    }

    .confetti {
        position: fixed;
        width: 12px;
        height: 12px;
        animation: confetti-fall linear forwards;
        border-radius: 2px;
        z-index: 9999;
    }

    .session-card-glow {
        animation: pulse-glow 3s ease-in-out infinite;
    }

    /* Custom scrollbar for better aesthetics */
    .custom-scrollbar::-webkit-scrollbar {
        width: 6px;
    }

    .custom-scrollbar::-webkit-scrollbar-track {
        @apply bg-gray-100 dark:bg-gray-800 rounded-full;
    }

    .custom-scrollbar::-webkit-scrollbar-thumb {
        @apply bg-gray-300 dark:bg-gray-600 rounded-full hover:bg-gray-400 dark:hover:bg-gray-500;
    }

    /* Enhanced animations for status transitions */
    .status-transition {
        transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .card-hover-effect {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .card-hover-effect:hover {
        transform: translateY(-2px);
    }
</style>