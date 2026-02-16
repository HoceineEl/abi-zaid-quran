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

        if ($this->shouldSkipPolling($record)) {
            return;
        }

        try {
            $whatsappService = app(WhatsAppService::class);
            $result = $whatsappService->getInstanceStatus($record->name);

            $previous = $record->status;

            $whatsappService->updateSessionFromApiStatus($record, $result);

            cache()->forget("error_count_{$record->id}");
            $this->updatePollMetrics($record);

            $record->refresh();
            $newInterval = $record->status->getPollingInterval();
            $this->dispatch('polling-interval-changed', ['interval' => $newInterval]);

            if ($previous !== $record->status) {
                if ($record->status === WhatsAppConnectionStatus::CONNECTED) {
                    Notification::make()
                        ->title('تم الاتصال بنجاح!')
                        ->body('جلسة واتساب متصلة وجاهزة للاستخدام')
                        ->success()
                        ->duration(5000)
                        ->send();

                    $this->dispatch('stop-polling');
                }

                $this->refreshTable();
            }
        } catch (\Throwable $e) {
            $this->handlePollingError($record, $e);
        }
    }

    protected function shouldSkipPolling(WhatsAppSession $record): bool
    {
        if ($record->status !== WhatsAppConnectionStatus::CONNECTED) {
            return false;
        }

        $lastChecked = cache()->get("last_poll_{$record->id}", now()->subMinutes(2));

        return now()->diffInSeconds($lastChecked) < 60;
    }

    protected function updatePollMetrics(WhatsAppSession $record): void
    {
        cache()->put("last_poll_{$record->id}", now(), now()->addHours(1));

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

        if ($errorCount === 1) {
            Notification::make()
                ->title('مشكلة في الاتصال')
                ->body('جاري إعادة المحاولة...')
                ->warning()
                ->duration(3000)
                ->send();
        }

        if ($errorCount >= 5) {
            $record->update([
                'status' => WhatsAppConnectionStatus::DISCONNECTED,
                'last_activity_at' => now(),
            ]);

            cache()->forget("error_count_{$record->id}");

            Notification::make()
                ->title('فقدان الاتصال')
                ->body('تعذر الاتصال بخادم واتساب. يرجى إعادة تشغيل الجلسة.')
                ->danger()
                ->persistent()
                ->send();

            $this->dispatch('stop-polling');
            $this->refreshTable();
        } else {
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

        $this->disconnectExistingSessions();

        try {
            $whatsappService = app(WhatsAppService::class);

            $whatsappService->startSessionAsync($record);

            Notification::make()
                ->title('جاري إعداد الجلسة...')
                ->body('سيظهر رمز QR خلال ثوانٍ قليلة')
                ->info()
                ->send();

            $record->refresh();
            $this->dispatch('polling-interval-changed', ['interval' => $record->status->getPollingInterval()]);

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
                    $this->cleanupAllExistingSessions();

                    $record = WhatsAppSession::create([
                        'user_id' => auth()->id(),
                        'name' => $data['name'],
                        'status' => WhatsAppConnectionStatus::DISCONNECTED,
                    ]);

                    try {
                        $whatsappService = app(WhatsAppService::class);

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
                // Silent fail
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
            $deletedCount = WhatsAppSession::where('user_id', auth()->id())->delete();

            if ($deletedCount > 0) {
                Notification::make()
                    ->title('تم تنظيف الجلسات السابقة')
                    ->body("تم حذف {$deletedCount} جلسة سابقة")
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
