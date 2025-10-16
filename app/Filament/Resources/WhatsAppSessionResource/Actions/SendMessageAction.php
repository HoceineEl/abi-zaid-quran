<?php

namespace App\Filament\Resources\WhatsAppSessionResource\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Exception;
use App\Models\WhatsAppMessageHistory;
use App\Models\WhatsAppSession;
use App\Services\WhatsAppService;
use Filament\Forms;
use Filament\Notifications\Notification;

class SendMessageAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'send_message';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('إرسال رسالة')
            ->icon('heroicon-o-paper-airplane')
            ->color('success')
            ->visible(fn (WhatsAppSession $record) => $record->isConnected())
            ->form([
                TextInput::make('recipient')
                    ->label('رقم هاتف المستقبل')
                    ->placeholder('966501234567')
                    ->required()
                    ->regex('/^[0-9]+$/')
                    ->helperText('أدخل رقم الهاتف مع رمز البلد (مثال: 966501234567)'),

                Textarea::make('message')
                    ->label('الرسالة')
                    ->placeholder('اكتب رسالتك هنا...')
                    ->required()
                    ->maxLength(4096)
                    ->rows(3),
            ])
            ->action(function (WhatsAppSession $record, array $data) {
                try {
                    if (!$record->isConnected()) {
                        throw new Exception('جلسة واتساب غير متصلة');
                    }

                    // Create message history record as queued
                    $messageHistory = WhatsAppMessageHistory::create([
                        'session_id' => $record->id,
                        'sender_user_id' => auth()->id(),
                        'recipient_phone' => $data['recipient'],
                        'message_type' => 'text',
                        'message_content' => $data['message'],
                        'status' => 'queued',
                    ]);

                    // Use defer() to send the message asynchronously
                    defer(function () use ($record, $data, $messageHistory) {
                        try {
                            $whatsappService = app(WhatsAppService::class);
                            $result = $whatsappService->sendTextMessage(
                                $record->id,
                                $data['recipient'],
                                $data['message']
                            );

                            // Update message history as sent
                            $messageHistory->update([
                                'status' => 'sent',
                                'whatsapp_message_id' => $result[0]['messageId'] ?? null,
                                'sent_at' => now(),
                            ]);

                        } catch (Exception $e) {
                            // Update message history as failed
                            $messageHistory->update([
                                'status' => 'failed',
                                'error_message' => $e->getMessage(),
                            ]);
                        }
                    });

                    Notification::make()
                        ->title('تم جدولة الرسالة للإرسال!')
                        ->body("سيتم إرسال الرسالة إلى {$data['recipient']} قريباً")
                        ->success()
                        ->send();
                } catch (Exception $e) {
                    Notification::make()
                        ->title('فشل في جدولة الرسالة')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }
}