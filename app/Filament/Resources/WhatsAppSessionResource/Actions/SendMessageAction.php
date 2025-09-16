<?php

namespace App\Filament\Resources\WhatsAppSessionResource\Actions;

use App\Models\WhatsAppMessageHistory;
use App\Models\WhatsAppSession;
use App\Services\WhatsAppService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;

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
                Forms\Components\TextInput::make('recipient')
                    ->label('رقم هاتف المستقبل')
                    ->placeholder('966501234567')
                    ->required()
                    ->regex('/^[0-9]+$/')
                    ->helperText('أدخل رقم الهاتف مع رمز البلد (مثال: 966501234567)'),

                Forms\Components\Textarea::make('message')
                    ->label('الرسالة')
                    ->placeholder('اكتب رسالتك هنا...')
                    ->required()
                    ->maxLength(4096)
                    ->rows(3),
            ])
            ->action(function (WhatsAppSession $record, array $data) {
                try {
                    if (!$record->isConnected()) {
                        throw new \Exception('جلسة واتساب غير متصلة');
                    }

                    $whatsappService = app(WhatsAppService::class);
                    $result = $whatsappService->sendTextMessage(
                        $record->id,
                        $data['recipient'],
                        $data['message']
                    );

                    // Create message history record
                    WhatsAppMessageHistory::create([
                        'session_id' => $record->id,
                        'sender_user_id' => auth()->id(),
                        'recipient_phone' => $data['recipient'],
                        'message_type' => 'text',
                        'message_content' => $data['message'],
                        'status' => 'sent',
                        'whatsapp_message_id' => $result[0]['messageId'] ?? null,
                        'sent_at' => now(),
                    ]);

                    Notification::make()
                        ->title('تم إرسال الرسالة بنجاح!')
                        ->body("تم إرسال الرسالة إلى {$data['recipient']}")
                        ->success()
                        ->send();
                } catch (\Exception $e) {
                    Notification::make()
                        ->title('فشل في إرسال الرسالة')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }
}