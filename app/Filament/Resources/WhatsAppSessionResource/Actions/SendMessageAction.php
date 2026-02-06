<?php

namespace App\Filament\Resources\WhatsAppSessionResource\Actions;

use App\Enums\WhatsAppMessageStatus;
use App\Jobs\SendWhatsAppMessageJob;
use App\Models\WhatsAppMessageHistory;
use App\Models\WhatsAppSession;
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
                    if (! $record->isConnected()) {
                        throw new \Exception('جلسة واتساب غير متصلة');
                    }

                    WhatsAppMessageHistory::create([
                        'session_id' => $record->id,
                        'sender_user_id' => auth()->id(),
                        'recipient_phone' => $data['recipient'],
                        'message_type' => 'text',
                        'message_content' => $data['message'],
                        'status' => WhatsAppMessageStatus::QUEUED,
                    ]);

                    SendWhatsAppMessageJob::dispatch(
                        $record->id,
                        $data['recipient'],
                        $data['message'],
                    );

                    Notification::make()
                        ->title('تم جدولة الرسالة للإرسال!')
                        ->body("سيتم إرسال الرسالة إلى {$data['recipient']} قريباً")
                        ->success()
                        ->send();
                } catch (\Exception $e) {
                    Notification::make()
                        ->title('فشل في جدولة الرسالة')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }
}
