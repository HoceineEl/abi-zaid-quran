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

        try {
            $whatsappService = app(WhatsAppService::class);
            $result = $whatsappService->getSessionStatus($record->id);

            $apiStatus = strtoupper($result['status'] ?? 'CONNECTING');
            $previous = $record->status;

            $modelStatus = WhatsAppConnectionStatus::fromApiStatus($apiStatus);

            // Update record based on status
            if ($apiStatus === 'CONNECTED') {
                $record->markAsConnected($result);
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

            // Refresh table if status changed
            if ($previous !== $record->status) {
                $this->refreshTable();
            }
        } catch (\Throwable) {
            // Silent fail to avoid disrupting UI
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

        // Disconnect other active sessions first
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
        } catch (\Exception $e) {
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
            } catch (\Exception $ex) {
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
                ->hidden(fn() => $this->hasActiveSession())
                ->form([
                    Forms\Components\TextInput::make('name')
                        ->label('اسم الجلسة')
                        ->maxLength(255)
                        ->required()
                        ->placeholder('أدخل اسم الجلسة')
                        ->default(fn() => 'جلسة واتساب - ' . auth()->user()->name),
                ])
                ->action(function (array $data) {
                    if ($this->hasActiveSession()) {
                        Notification::make()
                            ->title('تحذير من وجود جلسة نشطة')
                            ->body('لديك بالفعل جلسة نشطة. يجب قطع الاتصال أولاً.')
                            ->warning()
                            ->send();

                        return;
                    }

                    $this->disconnectExistingSessions();

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

    protected function refreshTable(): void
    {
        $this->dispatch('$refresh');
    }
}