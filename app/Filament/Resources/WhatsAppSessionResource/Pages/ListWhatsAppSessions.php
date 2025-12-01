<?php

namespace App\Filament\Resources\WhatsAppSessionResource\Pages;

use App\Enums\WhatsAppConnectionStatus;
use App\Filament\Resources\WhatsAppSessionResource;
use App\Models\WhatsAppSession;
use App\Services\WhatsAppService;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListWhatsAppSessions extends ListRecords
{
    protected static string $resource = WhatsAppSessionResource::class;

    protected $listeners = [
        'start-session' => 'startSessionForQr',
    ];

    public function pollStatus(string $sessionId): void
    {
        $record = WhatsAppSession::query()
            ->forUser(auth()->id())
            ->find($sessionId);

        if (! $record) {
            return;
        }

        // Smart polling: skip if session is stable (connected/disconnected) and last check was recent
        if ($this->shouldSkipPolling($record)) {
            return;
        }

        try {
            $whatsappService = app(WhatsAppService::class);
            $result = $whatsappService->getSessionStatus($record->id);

            $previous = $record->status;

            // Use strict status update
            $this->updateSessionStatusStrict($record, $result);

            // Reset error count on successful poll
            cache()->forget("error_count_{$record->id}");

            // Update poll count for smart polling decisions
            $this->updatePollMetrics($record);

            // Dispatch new polling interval based on current status
            $record->refresh();
            $newInterval = $record->status->getPollingInterval();
            $this->dispatch('polling-interval-changed', ['interval' => $newInterval]);

            // Refresh table if status changed
            if ($previous !== $record->status) {
                $this->refreshTable();
            }
        } catch (\Throwable $e) {
            // Handle persistent connection errors
            $this->handlePollingError($record, $e);
        }
    }

    /**
     * Strictly validate and update session status from API result.
     * Only marks as CONNECTED when API explicitly confirms with proper verification.
     */
    protected function updateSessionStatusStrict(WhatsAppSession $record, array $apiResult): void
    {
        $apiStatus = strtoupper($apiResult['status'] ?? 'UNKNOWN');
        $newModelStatus = WhatsAppConnectionStatus::fromApiStatus($apiStatus);
        $currentStatus = $record->status;

        \Log::info('WhatsApp status check', [
            'session_id' => $record->id,
            'api_status' => $apiStatus,
            'has_me' => isset($apiResult['me']),
            'has_user' => isset($apiResult['user']),
            'has_token' => ! empty($apiResult['token']),
            'has_qr' => ! empty($apiResult['qr']),
        ]);

        // CRITICAL: Only mark as CONNECTED if API explicitly confirms AND has verified connection
        if ($newModelStatus === WhatsAppConnectionStatus::CONNECTED) {
            if ($apiStatus !== 'CONNECTED') {
                \Log::warning('Prevented false connected status - API status mismatch', [
                    'session_id' => $record->id,
                    'api_status' => $apiStatus,
                ]);

                return;
            }

            // Verify real connection by checking for 'me' field (connected phone info)
            // or 'user' field - these indicate actual WhatsApp connection, not just session creation
            $hasPhoneInfo = ! empty($apiResult['me']) || ! empty($apiResult['user']);
            $hasToken = ! empty($apiResult['token']);

            if (! $hasPhoneInfo && ! $hasToken) {
                \Log::warning('Connected status without phone verification - treating as pending', [
                    'session_id' => $record->id,
                    'api_result_keys' => array_keys($apiResult),
                ]);
                $newModelStatus = WhatsAppConnectionStatus::PENDING;
            }

            // If there's still a QR code in the response, we're not truly connected yet
            if (! empty($apiResult['qr'])) {
                \Log::warning('Connected status but QR still present - treating as pending', [
                    'session_id' => $record->id,
                ]);
                $newModelStatus = WhatsAppConnectionStatus::PENDING;
            }
        }

        // Validate transition (but allow it if transitioning to same status)
        if ($currentStatus !== $newModelStatus && ! $currentStatus->canTransitionTo($newModelStatus)) {
            \Log::warning('Invalid status transition blocked', [
                'session_id' => $record->id,
                'from' => $currentStatus->value,
                'to' => $newModelStatus->value,
            ]);

            return;
        }

        // Perform update
        if ($newModelStatus === WhatsAppConnectionStatus::CONNECTED) {
            $record->markAsConnected($apiResult);

            // Send notification for successful connection
            Notification::make()
                ->title('تم الاتصال بنجاح!')
                ->body('جلسة واتساب متصلة وجاهزة للاستخدام')
                ->success()
                ->duration(5000)
                ->send();

            // Stop polling since we're connected
            $this->dispatch('stop-polling');
        } else {
            $record->update([
                'status' => $newModelStatus,
                'last_activity_at' => now(),
            ]);

            // Only update QR code if:
            // 1. We don't have one yet, OR
            // 2. The new one is different AND current QR is older than 2 minutes
            $this->updateQrCodeIfNeeded($record, $apiResult);
        }
    }

    /**
     * Update QR code only if needed - avoid unnecessary regeneration
     */
    protected function updateQrCodeIfNeeded(WhatsAppSession $record, array $apiResult): void
    {
        if (empty($apiResult['qr'])) {
            return;
        }

        $newQr = $apiResult['qr'];
        $currentQr = $record->qr_code;

        // If we don't have a QR code yet, set it
        if (empty($currentQr)) {
            $record->updateQrCode($newQr);
            \Log::info('QR code set (first time)', ['session_id' => $record->id]);

            return;
        }

        // Check if QR is actually different (ignore minor differences)
        // QR codes from WhatsApp typically change every ~20 seconds for security
        // We cache ours for 2 minutes to reduce flicker
        $qrCacheKey = "qr_updated_at_{$record->id}";
        $lastQrUpdate = cache()->get($qrCacheKey);

        if ($lastQrUpdate && now()->diffInSeconds($lastQrUpdate) < 120) {
            // QR was updated less than 2 minutes ago, keep current one
            return;
        }

        // Update QR code and cache the timestamp
        if ($currentQr !== $newQr) {
            $record->updateQrCode($newQr);
            cache()->put($qrCacheKey, now(), now()->addMinutes(5));
            \Log::info('QR code updated (after cache expiry)', ['session_id' => $record->id]);
        }
    }

    protected function shouldSkipPolling(WhatsAppSession $record): bool
    {

        // Skip polling for stable states if checked recently (within last minute)
        if (in_array($record->status, [
            WhatsAppConnectionStatus::CONNECTED,
        ])) {
            $lastChecked = cache()->get("last_poll_{$record->id}", now()->subMinutes(2));

            return now()->diffInSeconds($lastChecked) < 60;
        }

        return false;
    }

    protected function updatePollMetrics(WhatsAppSession $record): void
    {
        cache()->put("last_poll_{$record->id}", now(), now()->addHours(1));

        // Track consecutive poll count for exponential backoff
        $pollCount = cache()->get("poll_count_{$record->id}", 0) + 1;
        cache()->put("poll_count_{$record->id}", $pollCount, now()->addHours(1));
    }

    protected function handlePollingError(WhatsAppSession $record, \Throwable $e): void
    {
        $errorCount = cache()->increment("error_count_{$record->id}");

        \Log::warning('WhatsApp polling error', [
            'session_id' => $record->id,
            'error_count' => $errorCount,
            'error' => $e->getMessage(),
        ]);

        // Show warning notification on first error
        if ($errorCount === 1) {
            Notification::make()
                ->title('مشكلة في الاتصال')
                ->body('جاري إعادة المحاولة...')
                ->warning()
                ->duration(3000)
                ->send();
        }

        // After 5 consecutive errors, mark as disconnected
        if ($errorCount >= 5) {
            $record->update([
                'status' => WhatsAppConnectionStatus::DISCONNECTED,
                'last_activity_at' => now(),
            ]);

            cache()->forget("error_count_{$record->id}");

            // Send error notification
            Notification::make()
                ->title('فقدان الاتصال')
                ->body('تعذر الاتصال بخادم واتساب. يرجى إعادة تشغيل الجلسة.')
                ->danger()
                ->persistent()
                ->send();

            // Stop polling
            $this->dispatch('stop-polling');
            $this->refreshTable();
        } else {
            // Exponential backoff: 3s, 6s, 12s, 24s
            $backoffInterval = min(3000 * pow(2, $errorCount - 1), 30000);
            $this->dispatch('polling-interval-changed', ['interval' => $backoffInterval]);
        }
    }

    public function startSessionForQr(array $data): void
    {
        $sessionId = $data['sessionId'] ?? null;

        if (! $sessionId) {
            return;
        }

        $record = WhatsAppSession::query()
            ->find($sessionId);

        if (! $record) {
            Notification::make()
                ->title('الجلسة غير موجودة')
                ->danger()
                ->send();

            return;
        }

        if (! $record->status->canStartSession()) {
            Notification::make()
                ->title('الجلسة نشطة بالفعل')
                ->body('هذه الجلسة نشطة بالفعل أو في حالة اتصال')
                ->warning()
                ->send();

            return;
        }

        // Clean up any existing sessions first
        $this->disconnectExistingSessions();

        try {
            $whatsappService = app(WhatsAppService::class);

            // Use async start - returns immediately, polling handles QR retrieval
            $whatsappService->startSessionAsync($record);

            Notification::make()
                ->title('جاري إعداد الجلسة...')
                ->body('سيظهر رمز QR خلال ثوانٍ قليلة')
                ->info()
                ->send();

            // Start polling for this session
            $record->refresh();
            $this->dispatch('polling-interval-changed', ['interval' => $record->status->getPollingInterval()]);

            // Refresh the table to show updated status
            $this->refreshTable();
        } catch (\Exception $e) {
            $record->markAsDisconnected();

            Notification::make()
                ->title('فشل في بدء الجلسة')
                ->body($e->getMessage())
                ->danger()
                ->send();

            $this->refreshTable();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create_session')
                ->label('إنشاء جلسة')
                ->icon('heroicon-o-plus')
                ->color('success')
                ->hidden(fn () => $this->hasActiveSession())
                ->form([
                    Forms\Components\TextInput::make('name')
                        ->label('اسم الجلسة')
                        ->maxLength(255)
                        ->required()
                        ->placeholder('أدخل اسم الجلسة')
                        ->default(fn () => 'جلسة واتساب - '.auth()->user()->name),
                ])
                ->action(function (array $data) {
                    // Always clean up existing sessions first (both active and soft-deleted)
                    $this->cleanupAllExistingSessions();

                    $record = WhatsAppSession::create([
                        'user_id' => auth()->id(),
                        'name' => $data['name'],
                        'status' => WhatsAppConnectionStatus::DISCONNECTED,
                    ]);

                    try {
                        $whatsappService = app(WhatsAppService::class);

                        // Use async start - returns immediately
                        $whatsappService->startSessionAsync($record);

                        Notification::make()
                            ->title('جاري إعداد الجلسة...')
                            ->body('سيظهر رمز QR خلال ثوانٍ قليلة')
                            ->info()
                            ->send();
                    } catch (\Exception $e) {
                        $record->markAsDisconnected();

                        Notification::make()
                            ->title('فشل في بدء الجلسة')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();

                        return;
                    }

                    $this->redirect(static::getUrl());
                }),
        ];
    }

    protected function hasActiveSession(): bool
    {
        return WhatsAppSession::query()
            ->forUser(auth()->id())
            ->active()
            ->exists();
    }

    protected function disconnectExistingSessions(): void
    {
        $existingSessions = WhatsAppSession::query()
            ->forUser(auth()->id())
            ->active()
            ->get();

        foreach ($existingSessions as $session) {
            try {
                $whatsappService = app(WhatsAppService::class);
                $whatsappService->logout($session);
            } catch (\Exception) {
                // Silent fail - no logging
            }

            $session->markAsDisconnected();
        }

        if ($existingSessions->isNotEmpty()) {
            Notification::make()
                ->title('تم قطع الجلسات الموجودة')
                ->body('تم قطع الاتصال مع الجلسات السابقة')
                ->warning()
                ->send();
        }
    }

    protected function cleanupAllExistingSessions(): void
    {
        try {
            // Delete all sessions for this user with cascade
            $deletedCount = WhatsAppSession::where('user_id', auth()->id())->delete();

            if ($deletedCount > 0) {
                Notification::make()
                    ->title('تم تنظيف الجلسات السابقة')
                    ->body("تم حذف {$deletedCount} جلسة سابقة لإنشاء جلسة جديدة")
                    ->info()
                    ->send();
            }
        } catch (\Exception $e) {
            \Log::warning('Failed to cleanup existing sessions', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function refreshTable(): void
    {
        $this->dispatch('$refresh');
    }
}
