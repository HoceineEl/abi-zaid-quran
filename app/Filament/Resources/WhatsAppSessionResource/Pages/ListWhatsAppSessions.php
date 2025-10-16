<?php

namespace App\Filament\Resources\WhatsAppSessionResource\Pages;

use App\Enums\WhatsAppConnectionStatus;
use App\Filament\Resources\WhatsAppSessionResource;
use App\Models\WhatsAppSession;
use App\Services\WhatsAppService;
use Exception;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Log;
use Throwable;

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

            $apiStatus = strtoupper($result['status'] ?? 'CONNECTING');
            $previous = $record->status;

            $modelStatus = WhatsAppConnectionStatus::fromApiStatus($apiStatus);

            // Update record based on status
            if ($apiStatus === 'CONNECTED') {
                $record->markAsConnected($result);

                // Send notification for successful connection
                Notification::make()
                    ->title('تم الاتصال بنجاح! 🎉')
                    ->body('جلسة واتساب متصلة وجاهزة للاستخدام')
                    ->success()
                    ->duration(5000)
                    ->send();
            } else {
                $record->update([
                    'status' => $modelStatus,
                    'last_activity_at' => now(),
                ]);

                // Only update QR code if it's new and provided
                if (isset($result['qr']) && ! empty($result['qr'])) {
                    if ($record->qr_code !== $result['qr']) {
                        $record->updateQrCode($result['qr']);
                    }
                }
            }

            // Update poll count for smart polling decisions
            $this->updatePollMetrics($record);

            // Refresh table if status changed
            if ($previous !== $record->status) {
                $this->refreshTable();
            }
        } catch (Throwable $e) {
            // Handle persistent connection errors
            $this->handlePollingError($record, $e);
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

    protected function handlePollingError(WhatsAppSession $record, Throwable $e): void
    {
        $errorCount = cache()->get("error_count_{$record->id}", 0) + 1;
        cache()->put("error_count_{$record->id}", $errorCount, now()->addHours(1));

        // After 3 consecutive errors, mark as disconnected and stop polling aggressively
        if ($errorCount >= 3) {
            $record->update([
                'status' => WhatsAppConnectionStatus::DISCONNECTED,
                'last_activity_at' => now(),
            ]);

            // Send error notification
            Notification::make()
                ->title('فقدان الاتصال')
                ->body('تم فقدان الاتصال مع خادم واتساب. يرجى إعادة تشغيل الجلسة.')
                ->warning()
                ->duration(8000)
                ->send();

            // Reset error count
            cache()->forget("error_count_{$record->id}");

            $this->refreshTable();
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

            // First try to get session status (reuse existing session if available)
            $result = $whatsappService->getSessionStatus($record->id);
            $apiStatus = strtoupper($result['status'] ?? 'PENDING');

            if ($apiStatus === 'CONNECTED') {
                $record->markAsConnected($result);

                Notification::make()
                    ->title('تم بدء الجلسة بنجاح')
                    ->success()
                    ->send();
            } else {
                $modelStatus = WhatsAppConnectionStatus::fromApiStatus($apiStatus);
                $record->update([
                    'status' => $modelStatus,
                    'session_data' => $result,
                    'last_activity_at' => now(),
                ]);

                if (! empty($result['qr'])) {
                    $record->updateQrCode($result['qr']);
                }

                Notification::make()
                    ->title('تم بدء الجلسة بنجاح')
                    ->body('يرجى مسح رمز QR')
                    ->success()
                    ->send();
            }

            // Refresh the table to show updated status
            $this->refreshTable();
        } catch (Exception $e) {
            try {
                // If getting status failed, try to start a new session
                $whatsappService = app(WhatsAppService::class);
                $whatsappService->startSession($record);

                Notification::make()
                    ->title('تم بدء الجلسة بنجاح')
                    ->body('يرجى مسح رمز QR')
                    ->success()
                    ->send();

                // Refresh the table to show updated status
                $this->refreshTable();
            } catch (Exception $ex) {
                $record->markAsDisconnected();

                Notification::make()
                    ->title('فشل في بدء الجلسة')
                    ->body($ex->getMessage())
                    ->danger()
                    ->send();
            }
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
                ->schema([
                    TextInput::make('name')
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
                        $whatsappService->startSession($record);

                        Notification::make()
                            ->title('تم بدء الجلسة بنجاح')
                            ->body('يرجى مسح رمز QR')
                            ->success()
                            ->send();
                    } catch (Exception $e) {
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
            } catch (Exception) {
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
        } catch (Exception $e) {
            Log::warning('Failed to cleanup existing sessions', [
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
