<?php

namespace App\Filament\Actions;

use App\Classes\Core;
use App\Enums\MessageResponseStatus;
use App\Enums\WhatsAppMessageStatus;
use App\Helpers\PhoneHelper;
use App\Jobs\SendWhatsAppMessageJob;
use App\Models\GroupMessageTemplate;
use App\Models\WhatsAppMessageHistory;
use App\Models\WhatsAppSession;
use Filament\Forms;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\BulkAction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class SendWhatsAppBulkToDisconnectedAction extends BulkAction
{
    public static function getDefaultName(): ?string
    {
        return 'send_whatsapp_bulk_to_disconnected';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('إرسال رسائل واتساب')
            ->icon('heroicon-o-paper-airplane')
            ->color('info')
            ->form($this->getFormFields())
            ->action(function (Collection $records, array $data) {
                $this->sendMessagesToMultiple($records, $data);
            })
            ->requiresConfirmation()
            ->modalHeading('إرسال رسائل واتساب للطلاب المحددين');
    }

    protected function getFormFields(): array
    {
        $isAdmin = auth()->user()->isAdministrator();

        $fields = [
            Forms\Components\ToggleButtons::make('message_type')
                ->label('نوع الرسالة')
                ->options([
                    'reminder' => 'الرسالة التذكيرية',
                    'warning' => 'الرسالة الإندارية',
                ])
                ->default('reminder')
                ->required()
                ->inline(),
        ];

        if ($isAdmin) {
            $fields[] = Forms\Components\Select::make('template_id')
                ->label('اختر قالب الرسالة')
                ->options(function () {
                    $templates = GroupMessageTemplate::pluck('name', 'id')->toArray();

                    return array_merge(['custom' => 'رسالة مخصصة'], $templates);
                })
                ->default('custom')
                ->live();
        }

        $fields[] = Textarea::make('message')
            ->hint('يمكنك استخدام المتغيرات التالية: {student_name}, {group_name}, {curr_date}, {last_presence}')
            ->default(<<<'ARABIC'
السلام عليكم ورحمة الله وبركاته
*أخي الطالب {student_name}*،
نذكرك بالواجب المقرر اليوم، لعل المانع خير.
بارك الله في وقتك وجهدك وزادك علماً ونفعاً. 🤲
ARABIC
            )
            ->label('الرسالة')
            ->required()
            ->rows(4)
            ->hidden(fn (Get $get) => $isAdmin && $get('template_id') !== 'custom');

        return $fields;
    }

    protected function sendMessagesToMultiple(Collection $disconnections, array $data): void
    {
        $session = WhatsAppSession::getUserSession(auth()->id());
        if (! $session || ! $session->isConnected()) {
            Notification::make()
                ->title('جلسة واتساب غير متصلة')
                ->body('يرجى التأكد من أن لديك جلسة واتساب متصلة قبل إرسال الرسائل')
                ->danger()
                ->send();

            return;
        }

        $messageResponseType = $data['message_type'] === 'warning'
            ? MessageResponseStatus::WarningMessage
            : MessageResponseStatus::ReminderMessage;

        $messagesQueued = 0;

        foreach ($disconnections as $disconnection) {
            $student = $disconnection->student;
            $group = $disconnection->group;

            $messageContent = $this->resolveMessageContent($data);
            $processedMessage = Core::processMessageTemplate($messageContent, $student, $group);

            $phoneNumber = PhoneHelper::cleanPhoneNumber($student->phone);

            if (! $phoneNumber) {
                Log::warning('Invalid phone number for disconnected student', [
                    'student_id' => $student->id,
                    'phone' => $student->phone,
                ]);

                continue;
            }

            try {
                WhatsAppMessageHistory::create([
                    'session_id' => $session->id,
                    'sender_user_id' => auth()->id(),
                    'recipient_phone' => $phoneNumber,
                    'message_type' => 'text',
                    'message_content' => $processedMessage,
                    'status' => WhatsAppMessageStatus::QUEUED,
                ]);

                SendWhatsAppMessageJob::dispatch(
                    $session->id,
                    $phoneNumber,
                    $processedMessage,
                    'text',
                    $student->id,
                    [
                        'sender_user_id' => auth()->id(),
                        'disconnection_id' => $disconnection->id,
                        'message_response_type' => $messageResponseType->value,
                    ],
                );

                $messagesQueued++;
            } catch (\Exception $e) {
                Log::error('Failed to queue message for disconnected student', [
                    'student_id' => $student->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Notification::make()
            ->title('تم جدولة الرسائل للإرسال!')
            ->body("تم جدولة $messagesQueued رسالة واتساب لإرسالها للطلاب المحددين")
            ->success()
            ->send();
    }

    protected function resolveMessageContent(array $data): string
    {
        $isAdmin = auth()->user()->isAdministrator();

        if ($isAdmin && isset($data['template_id']) && $data['template_id'] === 'custom') {
            return $data['message'];
        }

        if ($isAdmin && isset($data['template_id'])) {
            $template = GroupMessageTemplate::find($data['template_id']);
            if ($template) {
                return $template->content;
            }
        }

        return $data['message'] ?? <<<'ARABIC'
السلام عليكم ورحمة الله وبركاته
*أخي الطالب {student_name}*،
نذكرك بالواجب المقرر اليوم، لعل المانع خير.
بارك الله في وقتك وجهدك وزادك علماً ونفعاً. 🤲
ARABIC;
    }
}
