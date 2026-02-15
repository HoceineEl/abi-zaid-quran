<?php

namespace App\Filament\Actions;

use App\Enums\WhatsAppConnectionStatus;
use App\Models\WhatsAppSession;
use App\Services\WhatsAppService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Cache;

class CheckWhatsAppStatusAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'check_whatsapp_status';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('فحص حالة الواتساب')
            ->icon('heroicon-o-arrow-path')
            ->color('info')
            ->action(function () {
                $session = WhatsAppSession::getUserSession(auth()->id());

                if (! $session) {
                    Notification::make()
                        ->title('لا توجد جلسة واتساب')
                        ->body('يرجى إنشاء جلسة واتساب أولاً')
                        ->warning()
                        ->send();

                    return;
                }

                self::checkAndUpdateStatus($session);
            });
    }

    public static function checkAndUpdateStatus(WhatsAppSession $session): void
    {
        try {
            $whatsappService = app(WhatsAppService::class);
            $result = $whatsappService->getSessionStatus($session->id);

            $apiStatus = strtoupper($result['status'] ?? 'DISCONNECTED');
            $modelStatus = WhatsAppConnectionStatus::fromApiStatus($apiStatus);

            if ($apiStatus === 'CONNECTED') {
                $session->markAsConnected($result);
            } elseif ($apiStatus === 'DISCONNECTED') {
                $session->markAsDisconnected();

                if (isset($result['detail']) && str_contains($result['detail'], 'not found')) {
                    Notification::make()
                        ->title('الجلسة غير موجودة')
                        ->body('الجلسة غير موجودة على خادم API')
                        ->warning()
                        ->send();

                    return;
                }
            } else {
                $session->update([
                    'status' => $modelStatus,
                    'session_data' => $result,
                    'last_activity_at' => now(),
                ]);

                if (! empty($result['qr'])) {
                    $session->updateQrCode($result['qr']);
                }

                if (! empty($result['token'])) {
                    Cache::put("whatsapp_token_{$session->id}", $result['token'], now()->addHours(24));
                }
            }

            Notification::make()
                ->title('تم تحديث الحالة')
                ->body("الحالة الحالية: {$modelStatus->getLabel()}")
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('فشل في فحص الحالة')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
