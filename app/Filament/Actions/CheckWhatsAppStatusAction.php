<?php

namespace App\Filament\Actions;

use App\Models\WhatsAppSession;
use App\Services\WhatsAppService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

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
            $result = $whatsappService->getInstanceStatus($session->name);

            $whatsappService->updateSessionFromApiStatus($session, $result);

            $session->refresh();

            Notification::make()
                ->title('تم تحديث الحالة')
                ->body("الحالة الحالية: {$session->status->getLabel()}")
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
