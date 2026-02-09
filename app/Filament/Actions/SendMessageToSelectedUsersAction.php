<?php

namespace App\Filament\Actions;

use App\Enums\WhatsAppMessageStatus;
use App\Helpers\PhoneHelper;
use App\Jobs\SendWhatsAppMessageJob;
use App\Models\User;
use App\Models\WhatsAppMessageHistory;
use App\Models\WhatsAppSession;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\BulkAction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class SendMessageToSelectedUsersAction extends BulkAction
{
    public static function getDefaultName(): ?string
    {
        return 'send_message_to_selected_users';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('أرسل إلى مستخدمين محدد')
            ->icon('heroicon-o-device-phone-mobile')
            ->color('success')
            ->form([
                Textarea::make('message_content')
                    ->label('نص الرسالة')
                    ->placeholder('اكتب رسالتك هنا...')
                    ->rows(8)
                    ->required()
                    ->helperText('سيتم إرسال هذه الرسالة لجميع المستخدمين المحددين عبر واتساب'),
            ])
            ->action(function (array $data, Collection $records) {
                $this->sendMessageToSelectedUsers($data, $records);
            })
            ->modalHeading('إرسال رسالة واتساب للمستخدمين المحددين')
            ->modalSubmitActionLabel('إرسال الرسالة')
            ->modalCancelActionLabel('إلغاء')
            ->modalWidth('lg')
            ->deselectRecordsAfterCompletion();
    }

    /**
     * Send messages to selected users
     */
    protected function sendMessageToSelectedUsers(array $data, Collection $records): void
    {
        if ($records->isEmpty()) {
            Notification::make()
                ->title('لا يوجد مستخدمين محددين')
                ->warning()
                ->send();
            return;
        }

        $this->sendMessageViaWhatsAppWeb($records, $data);
    }

    /**
     * Send messages via WhatsApp Web using queue jobs
     */
    protected function sendMessageViaWhatsAppWeb(Collection $users, array $data): void
    {
        // Get the current user's active session
        $session = WhatsAppSession::getUserSession(auth()->id());

        if (!$session || !$session->isConnected()) {
            Notification::make()
                ->title('جلسة واتساب غير متصلة')
                ->body('يرجى التأكد من أن لديك جلسة واتساب متصلة قبل إرسال الرسائل')
                ->danger()
                ->send();
            return;
        }

        $messageContent = $data['message_content'];
        $messagesQueued = 0;

        foreach ($users as $user) {
            // Clean phone number using phone helper (remove + sign)
            try {
                $phoneNumber = str_replace('+', '', phone($user->phone, 'MA')->formatE164());
            } catch (\Exception $e) {
                $phoneNumber = null;
            }

            if (!$phoneNumber) {
                Log::warning('Invalid phone number for user in bulk message', [
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'phone' => $user->phone,
                ]);
                continue;
            }

            try {
                // Create message history record as queued
                WhatsAppMessageHistory::create([
                    'session_id' => $session->id,
                    'sender_user_id' => auth()->id(),
                    'recipient_phone' => $phoneNumber,
                    'message_type' => 'text',
                    'message_content' => $messageContent,
                    'status' => WhatsAppMessageStatus::QUEUED,
                ]);

                $delay = SendWhatsAppMessageJob::getStaggeredDelay($session->id);

                SendWhatsAppMessageJob::dispatch(
                    $session->id,
                    $phoneNumber,
                    $messageContent,
                    'text',
                    null,
                    ['sender_user_id' => auth()->id()],
                )->delay(now()->addSeconds($delay));

                $messagesQueued++;

            } catch (\Exception $e) {
                Log::error('Failed to queue bulk message for user', [
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Notification::make()
            ->title('تم جدولة الرسائل للإرسال!')
            ->body("تم جدولة {$messagesQueued} رسالة لإرسالها للمستخدمين المحددين")
            ->success()
            ->send();
    }

}