<?php

namespace App\Filament\Actions;

use App\Classes\Core;
use App\Enums\WhatsAppMessageStatus;
use App\Helpers\PhoneHelper;
use App\Models\GroupMessageTemplate;
use App\Models\Student;
use App\Models\WhatsAppMessageHistory;
use App\Models\WhatsAppSession;
use App\Services\WhatsAppService;
use App\Traits\HandlesWhatsAppProgress;
use Filament\Forms;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class SendAbsentStudentsMessageAction extends Action
{
    use HandlesWhatsAppProgress;
    public static function getDefaultName(): ?string
    {
        return 'send_absent_students_message';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('إرسال رسالة تذكير للغائبين')
            ->icon('heroicon-o-chat-bubble-oval-left')
            ->color('danger')
            ->form(function() {
                $fields = [];
                $isAdmin = auth()->user()->isAdministrator();
                $ownerRecord = $this->getLivewire()->ownerRecord ?? $this->getRecord();
                $defaultTemplate = null;

                if ($ownerRecord && method_exists($ownerRecord, 'messageTemplates')) {
                    $defaultTemplate = $ownerRecord->messageTemplates()->wherePivot('is_default', true)->first();
                }

                // Only show template selection for admins
                if ($isAdmin) {
                    $fields[] = Forms\Components\Select::make('template_id')
                        ->label('اختر قالب الرسالة')
                        ->options(function () use ($ownerRecord) {
                            if ($ownerRecord && method_exists($ownerRecord, 'messageTemplates')) {
                                return $ownerRecord->messageTemplates()->pluck('group_message_templates.name', 'group_message_templates.id')
                                    ->prepend('رسالة مخصصة', 'custom');
                            }
                            return ['custom' => 'رسالة مخصصة'];
                        })
                        ->default(function () use ($defaultTemplate) {
                            return $defaultTemplate ? $defaultTemplate->id : 'custom';
                        })
                        ->reactive();
                }

                // Show message field for admins when custom is selected, or always for non-admins without default template
                $showMessageField = $isAdmin || !$defaultTemplate;

                if ($showMessageField) {
                    $defaultMessage = $defaultTemplate ? $defaultTemplate->content : 'السلام عليكم ورحمة الله وبركاته، {student_name} لم تنس واجب اليوم، لعل المانع خير.';

                    $fields[] = Textarea::make('message')
                        ->hint('يمكنك استخدام المتغيرات التالية: {student_name}, {group_name}, {curr_date}, {last_presence}')
                        ->default($defaultMessage)
                        ->label('الرسالة')
                        ->required()
                        ->rows(4)
                        ->hidden(fn(Get $get) => $isAdmin && $get('template_id') !== 'custom');
                }

                return $fields;
            })
            ->action(function (array $data) {
                $this->sendMessagesToAbsentStudents($data);
            });
    }

    /**
     * Send messages to absent students
     */
    protected function sendMessagesToAbsentStudents(array $data): void
    {
        $ownerRecord = $this->getLivewire()->ownerRecord ?? $this->getRecord();
        $selectedDate = $this->getSelectedDate();

        // Get absent students
        $absentStudents = $this->getAbsentStudents($ownerRecord, $selectedDate);

        if ($absentStudents->isEmpty()) {
            Notification::make()
                ->title('لا يوجد طلاب غائبين')
                ->body('لم يتم العثور على طلاب غائبين في التاريخ المحدد')
                ->warning()
                ->send();
            return;
        }

        // Get the message content
        $messageTemplate = $this->getMessageTemplate($data, $ownerRecord);

        $this->sendViaWhatsAppWeb($absentStudents, $messageTemplate, $data, $ownerRecord);
    }

    /**
     * Get absent students for the selected date
     */
    protected function getAbsentStudents($ownerRecord, string $selectedDate): Collection
    {
        if (!$ownerRecord || !method_exists($ownerRecord, 'students')) {
            return collect();
        }

        return $ownerRecord->students->filter(function ($student) use ($selectedDate) {
            return $student->progresses
                ->where('date', $selectedDate)
                ->where('status', 'absent')
                ->where('with_reason', false)
                ->count() > 0;
        });
    }

    /**
     * Get the selected date from table filters or default to today
     */
    protected function getSelectedDate(): string
    {
        $livewire = $this->getLivewire();
        if (method_exists($livewire, 'getTableFilters') && isset($livewire->tableFilters['date']['value'])) {
            return $livewire->tableFilters['date']['value'];
        }
        return now()->format('Y-m-d');
    }

    /**
     * Get the message template content
     */
    protected function getMessageTemplate(array $data, $ownerRecord): string
    {
        $isAdmin = auth()->user()->isAdministrator();

        // For non-admins, always use default template if available
        if (!$isAdmin && $ownerRecord && method_exists($ownerRecord, 'messageTemplates')) {
            $defaultTemplate = $ownerRecord->messageTemplates()->wherePivot('is_default', true)->first();
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

        return $data['message'] ?? 'السلام عليكم، لم تنس واجب اليوم، لعل المانع خير.';
    }

    /**
     * Send messages via WhatsApp Web (new service with defer)
     */
    protected function sendViaWhatsAppWeb(Collection $absentStudents, string $messageTemplate, array $data, $ownerRecord): void
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

        // Use defer() to process all messages asynchronously
        defer(function () use ($absentStudents, $messageTemplate, $ownerRecord, $session) {
            $messagesSent = 0;
            $messagesQueued = 0;
            $messageIndex = 0;

            foreach ($absentStudents as $student) {
                // Process message template with variables
                $processedMessage = Core::processMessageTemplate($messageTemplate, $student, $ownerRecord);

                // Clean phone number using phone helper (remove + sign)
                try {
                    $phoneNumber = str_replace('+', '', phone($student->phone, 'MA')->formatE164());
                } catch (\Exception $e) {
                    $phoneNumber = null;
                }

                if (!$phoneNumber) {
                    Log::warning('Invalid phone number for student', [
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
                        $processedMessage
                    );

                    // Update message history as sent
                    $messageHistory->update([
                        'status' => WhatsAppMessageStatus::SENT,
                        'whatsapp_message_id' => $result[0]['messageId'] ?? null,
                        'sent_at' => now(),
                    ]);

                    // Create or update progress record using trait
                    $this->createWhatsAppProgressRecord($student);

                    Log::info('WhatsApp message sent to absent student', [
                        'student_phone' => $phoneNumber,
                        'message_id' => $result[0]['messageId'] ?? null,
                        'message_index' => $messageIndex,
                        'delay_seconds' => $delaySeconds,
                    ]);

                    $messagesQueued++;
                    $messageIndex++;

                } catch (\Exception $e) {
                    // Update message history as failed if it was created
                    if (isset($messageHistory)) {
                        $messageHistory->update([
                            'status' => WhatsAppMessageStatus::FAILED,
                            'error_message' => $e->getMessage(),
                        ]);
                    }

                    Log::error('Failed to send WhatsApp message to absent student', [
                        'student_phone' => $phoneNumber,
                        'student_id' => $student->id,
                        'error' => $e->getMessage(),
                        'message_index' => $messageIndex,
                    ]);
                }
            }
        });

        $messagesQueued = $absentStudents->count();

        Notification::make()
            ->title('تم جدولة الرسائل للإرسال!')
            ->body("تم جدولة {$messagesQueued} رسالة لإرسالها للطلاب الغائبين")
            ->success()
            ->send();
    }

    /**
     * Send messages via legacy WhatsApp service (for compatibility)
     */
    protected function sendViaLegacyWhatsApp(Collection $absentStudents, string $messageTemplate, $ownerRecord): void
    {
        foreach ($absentStudents as $student) {
            $processedMessage = Core::processMessageTemplate($messageTemplate, $student, $ownerRecord);
            Core::sendSpecifMessageToStudent($student, $processedMessage);
        }

        Notification::make()
            ->title('تم إرسال الرسائل!')
            ->body("تم إرسال الرسائل لـ {$absentStudents->count()} طالب غائب")
            ->success()
            ->send();
    }

}