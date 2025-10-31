<?php

namespace App\Filament\Actions;

use App\Classes\Core;
use App\Enums\MessageResponseStatus;
use App\Enums\WhatsAppMessageStatus;
use App\Helpers\PhoneHelper;
use App\Models\GroupMessageTemplate;
use App\Models\StudentDisconnection;
use App\Models\WhatsAppMessageHistory;
use App\Models\WhatsAppSession;
use App\Services\WhatsAppService;
use App\Traits\HandlesWhatsAppProgress;
use Filament\Forms;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\BulkAction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class SendWhatsAppBulkToDisconnectedAction extends BulkAction
{
    use HandlesWhatsAppProgress;

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

        // Template selection for admins only
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

        // Show message field for admins when custom is selected, or for non-admins
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
        // Check WhatsApp session
        $session = WhatsAppSession::getUserSession(auth()->id());
        if (!$session || !$session->isConnected()) {
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
        $messageIndex = 0;

        foreach ($disconnections as $disconnection) {
            $student = $disconnection->student;
            $group = $disconnection->group;

            // Get message content
            $messageContent = $this->getMessageContent($data);

            // Process template variables
            $processedMessage = Core::processMessageTemplate($messageContent, $student, $group);

            // Clean phone number
            $phoneNumber = PhoneHelper::cleanPhoneNumber($student->phone);

            if (!$phoneNumber) {
                Log::warning('Invalid phone number for disconnected student', [
                    'student_id' => $student->id,
                    'student_name' => $student->name,
                    'phone' => $student->phone,
                ]);
                continue;
            }

            try {
                // Create message history record as queued
                $messageHistory = WhatsAppMessageHistory::create([
                    'session_id' => $session->id,
                    'sender_user_id' => auth()->id(),
                    'recipient_phone' => $phoneNumber,
                    'message_type' => 'text',
                    'message_content' => $processedMessage,
                    'status' => WhatsAppMessageStatus::QUEUED,
                ]);

                // Use defer() to send message asynchronously with rate limiting
                defer(function () use ($session, $phoneNumber, $processedMessage, $messageHistory, $disconnection, $messageResponseType, $student, $messageIndex) {
                    try {
                        // Calculate staggered delay for rate limiting
                        $delaySeconds = ceil($messageIndex / 10) * 0.5;

                        if ($delaySeconds > 0) {
                            usleep($delaySeconds * 1000000);
                        }

                        $whatsappService = app(WhatsAppService::class);
                        $result = $whatsappService->sendTextMessage(
                            $session->id,
                            $phoneNumber,
                            $processedMessage
                        );

                        // Update message history as sent
                        $messageHistory->update([
                            'status' => WhatsAppMessageStatus::SENT,
                            'whatsapp_message_id' => $result[0]['messageId'] ?? null,
                            'sent_at' => now(),
                        ]);

                        // Auto-update disconnection record
                        $updateData = [
                            'contact_date' => now()->format('Y-m-d'),
                            'message_response' => $messageResponseType,
                        ];

                        // Set the appropriate message date based on type
                        if ($messageResponseType === MessageResponseStatus::ReminderMessage) {
                            $updateData['reminder_message_date'] = now()->format('Y-m-d');
                        } elseif ($messageResponseType === MessageResponseStatus::WarningMessage) {
                            $updateData['warning_message_date'] = now()->format('Y-m-d');
                        }

                        $disconnection->update($updateData);

                        // Create progress record
                        $this->createWhatsAppProgressRecord($student);

                        Log::info('WhatsApp bulk message sent to disconnected student', [
                            'student_id' => $student->id,
                            'student_phone' => $phoneNumber,
                            'message_type' => $data['message_type'],
                            'message_index' => $messageIndex,
                            'delay_seconds' => $delaySeconds,
                        ]);

                    } catch (\Exception $e) {
                        // Update message history as failed
                        $messageHistory->update([
                            'status' => WhatsAppMessageStatus::FAILED,
                            'error_message' => $e->getMessage(),
                        ]);

                        Log::error('Failed to send WhatsApp bulk message to disconnected student', [
                            'student_id' => $student->id,
                            'student_phone' => $phoneNumber,
                            'error' => $e->getMessage(),
                            'message_index' => $messageIndex,
                        ]);
                    }
                });

                $messagesQueued++;
                $messageIndex++;

            } catch (\Exception $e) {
                Log::error('Failed to queue WhatsApp bulk message for disconnected student', [
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

    protected function getMessageContent(array $data): string
    {
        $isAdmin = auth()->user()->isAdministrator();

        // For admins
        if ($isAdmin && isset($data['template_id']) && $data['template_id'] === 'custom') {
            return $data['message'];
        }

        if ($isAdmin && isset($data['template_id'])) {
            $template = GroupMessageTemplate::find($data['template_id']);
            if ($template) {
                return $template->content;
            }
        }

        // Default message
        return $data['message'] ?? <<<'ARABIC'
السلام عليكم ورحمة الله وبركاته
*أخي الطالب {student_name}*،
نذكرك بالواجب المقرر اليوم، لعل المانع خير.
بارك الله في وقتك وجهدك وزادك علماً ونفعاً. 🤲
ARABIC;
    }
}
