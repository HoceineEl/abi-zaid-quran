<?php

namespace App\Filament\Actions;

use Filament\Actions\BulkAction;
use Filament\Schemas\Components\Utilities\Get;
use Exception;
use App\Classes\Core;
use App\Enums\WhatsAppMessageStatus;
use App\Helpers\PhoneHelper;
use App\Models\Group;
use App\Models\GroupMessageTemplate;
use App\Models\Progress;
use App\Models\Student;
use App\Models\WhatsAppMessageHistory;
use App\Models\WhatsAppSession;
use App\Services\WhatsAppService;
use App\Traits\HandlesWhatsAppProgress;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class SendWhatsAppMessageToSelectedStudentsAction extends BulkAction
{
    use HandlesWhatsAppProgress;

    protected ?Group $ownerRecord = null;

    public static function getDefaultName(): ?string
    {
        return 'send_whatsapp_to_selected_students';
    }

    public function ownerRecord(Group $record): static
    {
        $this->ownerRecord = $record;
        return $this;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('إرسال واتساب للطلاب المحددين')
            ->icon('heroicon-o-chat-bubble-left-ellipsis')
            ->color('success')
            ->form(function () {
                $fields = [];

                $isAdmin = auth()->user()->isAdministrator();
                $defaultTemplate = null;

                if ($this->ownerRecord) {
                    $defaultTemplate = $this->ownerRecord->messageTemplates()->wherePivot('is_default', true)->first();
                }

                // Only show template selection for admins
                if ($this->ownerRecord && $isAdmin) {
                    $fields[] = Select::make('template_id')
                        ->label('اختر قالب الرسالة')
                        ->options(function () {
                            return $this->ownerRecord->messageTemplates()
                                ->pluck('group_message_templates.name', 'group_message_templates.id')
                                ->prepend('رسالة مخصصة', 'custom');
                        })
                        ->default(function () use ($defaultTemplate) {
                            return $defaultTemplate ? $defaultTemplate->id : 'custom';
                        })
                        ->reactive();
                }

                // Show message field for admins when custom is selected, or always for non-admins without default template
                $showMessageField = $isAdmin || !$defaultTemplate;

                if ($showMessageField) {
                    $defaultMessage = $defaultTemplate ? $defaultTemplate->content : 'السلام عليكم، نذكركم بالواجب المقرر اليوم، لعل المانع خير.';

                    $fields[] = Textarea::make('message')
                        ->hint('يمكنك استخدام المتغيرات التالية: {{student_name}}, {{group_name}}, {{curr_date}}, {{prefix}}, {{pronoun}}, {{verb}}')
                        ->default($defaultMessage)
                        ->label('الرسالة')
                        ->placeholder('اكتب رسالتك هنا...')
                        ->rows(8)
                        ->required()
                        ->hidden(fn(Get $get) => $isAdmin && $this->ownerRecord && $get('template_id') !== 'custom');
                }

                return $fields;
            })
            ->action(function (array $data, Collection $records) {
                $this->sendMessageToSelectedStudents($data, $records);
            })
            ->modalHeading('إرسال رسالة واتساب للطلاب المحددين')
            ->modalSubmitActionLabel('إرسال الرسالة')
            ->modalCancelActionLabel('إلغاء')
            ->modalWidth('lg')
            ->requiresConfirmation()
            ->deselectRecordsAfterCompletion();
    }

    /**
     * Send messages to selected students
     */
    protected function sendMessageToSelectedStudents(array $data, Collection $records): void
    {
        if ($records->isEmpty()) {
            Notification::make()
                ->title('لا يوجد طلاب محددين')
                ->warning()
                ->send();
            return;
        }

        $isAdmin = auth()->user()->isAdministrator();
        $messageTemplate = '';

        if ($this->ownerRecord) {
            // For non-admins, always use default template if available
            if (!$isAdmin) {
                $defaultTemplate = $this->ownerRecord->messageTemplates()->wherePivot('is_default', true)->first();
                if ($defaultTemplate) {
                    $messageTemplate = $defaultTemplate->content;
                } else {
                    $messageTemplate = $data['message'] ?? 'السلام عليكم، نذكركم بالواجب المقرر اليوم، لعل المانع خير.';
                }
            } else {
                // Admins can choose template
                if (isset($data['template_id']) && $data['template_id'] === 'custom') {
                    $messageTemplate = $data['message'];
                } elseif (isset($data['template_id'])) {
                    $template = GroupMessageTemplate::find($data['template_id']);
                    if ($template) {
                        $messageTemplate = $template->content;
                    } else {
                        $messageTemplate = $data['message'] ?? 'السلام عليكم، نذكركم بالواجب المقرر اليوم، لعل المانع خير.';
                    }
                } else {
                    $messageTemplate = $data['message'] ?? 'السلام عليكم، نذكركم بالواجب المقرر اليوم، لعل المانع خير.';
                }
            }
        } else {
            $messageTemplate = $data['message'] ?? $data['message_content'] ?? 'السلام عليكم، نذكركم بالواجب المقرر اليوم، لعل المانع خير.';
        }

        $this->sendMessageViaWhatsAppWeb($records, $messageTemplate);
    }

    /**
     * Send messages via WhatsApp Web (new service with defer)
     */
    protected function sendMessageViaWhatsAppWeb(Collection $students, string $messageTemplate): void
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

        $messagesQueued = 0;
        $messageIndex = 0;
        $failedPhones = [];

        foreach ($students as $student) {
            // Ensure we have a Student model
            if (!($student instanceof Student)) {
                $student = Student::find($student);
                if (!$student) {
                    continue;
                }
            }

            // Process message template with student variables
            $messageContent = $messageTemplate;
            if ($this->ownerRecord) {
                $messageContent = Core::processMessageTemplate($messageTemplate, $student, $this->ownerRecord);
            }

            // Clean phone number using phone helper (remove + sign)
            try {
                $phoneNumber = str_replace('+', '', phone($student->phone, 'MA')->formatE164());
            } catch (Exception $e) {
                $phoneNumber = null;
            }

            if (!$phoneNumber) {
                Log::warning('Invalid phone number for student in bulk message', [
                    'student_id' => $student->id,
                    'student_name' => $student->name,
                    'phone' => $student->phone,
                ]);
                $failedPhones[] = $student->name;
                continue;
            }

            try {
                // Create message history record as queued
                $messageHistory = WhatsAppMessageHistory::create([
                    'session_id' => $session->id,
                    'sender_user_id' => auth()->id(),
                    'recipient_phone' => $phoneNumber,
                    'message_type' => 'text',
                    'message_content' => $messageContent,
                    'status' => WhatsAppMessageStatus::QUEUED,
                    'metadata' => [
                        'student_id' => $student->id,
                        'student_name' => $student->name,
                        'group_id' => $student->group_id,
                    ],
                ]);

                // Use defer() to send the message asynchronously with minimal rate limiting
                defer(function () use ($session, $phoneNumber, $messageContent, $messageHistory, $student, $messageIndex) {
                    try {
                        // Calculate staggered delay based on message position for rate limiting
                        $delaySeconds = ceil($messageIndex / 10) * 0.5; // 0.5 second delay for every 10 messages

                        // Minimal rate limiting: Only add delay for batches to prevent spam detection
                        if ($delaySeconds > 0) {
                            usleep($delaySeconds * 1000000); // Convert to microseconds for sub-second delays
                        }

                        $whatsappService = app(WhatsAppService::class);
                        $result = $whatsappService->sendTextMessage(
                            $session->id,
                            $phoneNumber,
                            $messageContent
                        );

                        // Update message history as sent
                        $messageHistory->update([
                            'status' => WhatsAppMessageStatus::SENT,
                            'whatsapp_message_id' => $result[0]['messageId'] ?? null,
                            'sent_at' => now(),
                        ]);

                        // Create or update progress record using trait
                        $this->createWhatsAppProgressRecord($student);

                        Log::info('Bulk message sent to student', [
                            'student_id' => $student->id,
                            'student_name' => $student->name,
                            'student_phone' => $phoneNumber,
                            'message_id' => $result[0]['messageId'] ?? null,
                            'message_index' => $messageIndex,
                            'delay_seconds' => $delaySeconds,
                        ]);

                    } catch (Exception $e) {
                        // Update message history as failed
                        $messageHistory->update([
                            'status' => WhatsAppMessageStatus::FAILED,
                            'error_message' => $e->getMessage(),
                        ]);

                        Log::error('Failed to send bulk message to student', [
                            'student_id' => $student->id,
                            'student_name' => $student->name,
                            'student_phone' => $phoneNumber,
                            'error' => $e->getMessage(),
                            'message_index' => $messageIndex,
                        ]);
                    }
                });

                $messagesQueued++;
                $messageIndex++;

            } catch (Exception $e) {
                Log::error('Failed to queue bulk message for student', [
                    'student_id' => $student->id,
                    'student_name' => $student->name,
                    'error' => $e->getMessage(),
                ]);
                $failedPhones[] = $student->name;
            }
        }

        // Show notification with results
        $notificationTitle = "تم جدولة {$messagesQueued} رسالة لإرسالها للطلاب المحددين";

        if (!empty($failedPhones)) {
            $notificationTitle .= "\n" . "فشل الإرسال لـ: " . implode(', ', $failedPhones);
        }

        Notification::make()
            ->title('تم جدولة الرسائل للإرسال!')
            ->body($notificationTitle)
            ->success()
            ->send();
    }
}