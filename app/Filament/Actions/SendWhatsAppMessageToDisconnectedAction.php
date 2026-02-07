<?php

namespace App\Filament\Actions;

use App\Classes\Core;
use App\Enums\MessageResponseStatus;
use App\Enums\WhatsAppMessageStatus;
use App\Helpers\PhoneHelper;
use App\Jobs\SendWhatsAppMessageJob;
use App\Models\GroupMessageTemplate;
use App\Models\StudentDisconnection;
use App\Models\WhatsAppMessageHistory;
use App\Models\WhatsAppSession;
use Filament\Forms;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class SendWhatsAppMessageToDisconnectedAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'send_whatsapp_to_disconnected';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('إرسال رسالة واتساب')
            ->icon('heroicon-o-paper-airplane')
            ->color('info')
            ->form(function (StudentDisconnection $record) {
                return $this->getFormFields($record);
            })
            ->action(function (StudentDisconnection $record, array $data) {
                $this->sendMessage($record, $data);
            });
    }

    protected function getFormFields(StudentDisconnection $record): array
    {
        $isAdmin = auth()->user()->isAdministrator();
        $group = $record->group;
        $defaultTemplate = null;
        $defaultTemplateId = 'custom';

        if ($group && method_exists($group, 'messageTemplates')) {
            $defaultTemplate = $group->messageTemplates()
                ->wherePivot('is_default', true)
                ->first();
            if ($defaultTemplate) {
                $defaultTemplateId = $defaultTemplate->id;
            }
        }

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

        // Template selection for admins
        if ($isAdmin && $group && method_exists($group, 'messageTemplates')) {
            $fields[] = Forms\Components\Select::make('template_id')
                ->label('اختر قالب الرسالة')
                ->options(function () use ($group) {
                    return $group->messageTemplates()
                        ->pluck('group_message_templates.name', 'group_message_templates.id')
                        ->prepend('رسالة مخصصة', 'custom')
                        ->toArray();
                })
                ->default($defaultTemplateId)
                ->live();
        }

        // Determine if we should show message field
        $showMessageField = $isAdmin || !$group || !method_exists($group, 'messageTemplates');

        if ($showMessageField) {

            // Use default or predefined message
            $defaultMessage = $defaultTemplate ? $defaultTemplate->content : <<<'ARABIC'
السلام عليكم ورحمة الله وبركاته
*أخي الطالب {student_name}*،
نذكرك بالواجب المقرر اليوم، لعل المانع خير.
بارك الله في وقتك وجهدك وزادك علماً ونفعاً. 🤲
ARABIC;

            $fields[] = Textarea::make('message')
                ->hint('يمكنك استخدام المتغيرات التالية: {student_name}, {group_name}, {curr_date}, {last_presence}')
                ->default($defaultMessage)
                ->label('الرسالة')
                ->required()
                ->rows(4)
                ->hidden(fn (Get $get) => $isAdmin && $get('template_id') !== 'custom');
        }

        return $fields;
    }

    protected function sendMessage(StudentDisconnection $record, array $data): void
    {
        $student = $record->student;
        $group = $record->group;

        // Check if student has a valid phone number
        $phoneNumber = PhoneHelper::cleanPhoneNumber($student->phone);
        if (!$phoneNumber) {
            Notification::make()
                ->title('رقم هاتف غير صالح')
                ->body('لا يمكن إرسال الرسالة، رقم الهاتف غير صالح')
                ->danger()
                ->send();
            return;
        }

        // Get WhatsApp session
        $session = WhatsAppSession::getUserSession(auth()->id());
        if (!$session || !$session->isConnected()) {
            Notification::make()
                ->title('جلسة واتساب غير متصلة')
                ->body('يرجى التأكد من أن لديك جلسة واتساب متصلة قبل إرسال الرسائل')
                ->danger()
                ->send();
            return;
        }

        // Get the message content
        $messageContent = $this->getMessageContent($data, $group);

        // Process template variables
        $processedMessage = Core::processMessageTemplate($messageContent, $student, $group);

        try {
            // Create message history record as queued
            WhatsAppMessageHistory::create([
                'session_id' => $session->id,
                'sender_user_id' => auth()->id(),
                'recipient_phone' => $phoneNumber,
                'message_type' => 'text',
                'message_content' => $processedMessage,
                'status' => WhatsAppMessageStatus::QUEUED,
            ]);

            // Determine message response type based on selected type
            $messageResponseType = $data['message_type'] === 'warning'
                ? MessageResponseStatus::WarningMessage
                : MessageResponseStatus::ReminderMessage;

            // Dispatch the job to send the message with rate limiting
            SendWhatsAppMessageJob::dispatch(
                $session->id,
                $phoneNumber,
                $processedMessage,
                'text',
                $student->id,
                [
                    'sender_user_id' => auth()->id(),
                    'disconnection_id' => $record->id,
                    'message_response_type' => $messageResponseType->value,
                ]
            );

            Notification::make()
                ->title('تم جدولة الرسالة للإرسال!')
                ->body('سيتم إرسال الرسالة إلى الطالب قريباً')
                ->success()
                ->send();

        } catch (\Exception $e) {
            Log::error('Failed to queue WhatsApp message for disconnected student', [
                'student_id' => $student->id,
                'error' => $e->getMessage(),
            ]);

            Notification::make()
                ->title('فشل في جدولة الرسالة')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function getMessageContent(array $data, $group): string
    {
        $isAdmin = auth()->user()->isAdministrator();

        // For non-admins, use default template if available
        if (!$isAdmin && $group && method_exists($group, 'messageTemplates')) {
            $defaultTemplate = $group->messageTemplates()
                ->wherePivot('is_default', true)
                ->first();
            if ($defaultTemplate) {
                return $defaultTemplate->content;
            }
        }

        // For admins or when no default template
        if (isset($data['template_id']) && $data['template_id'] === 'custom') {
            return $data['message'];
        }

        if (isset($data['template_id'])) {
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
