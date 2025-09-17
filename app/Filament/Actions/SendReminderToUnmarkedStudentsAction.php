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
use Filament\Forms;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class SendReminderToUnmarkedStudentsAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'send_reminder_to_unmarked';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('تذكير الطلاب غير المسجلين')
            ->icon('heroicon-o-megaphone')
            ->color('info')
            ->form([
                Forms\Components\Select::make('template_id')
                    ->label('اختر قالب الرسالة')
                    ->options(function () {
                        $ownerRecord = $this->getLivewire()->ownerRecord ?? $this->getRecord();
                        if ($ownerRecord && method_exists($ownerRecord, 'messageTemplates')) {
                            return $ownerRecord->messageTemplates()->pluck('name', 'group_message_templates.id')
                                ->prepend('رسالة مخصصة', 'custom');
                        }
                        return ['custom' => 'رسالة مخصصة'];
                    })
                    ->default(function () {
                        $ownerRecord = $this->getLivewire()->ownerRecord ?? $this->getRecord();
                        if ($ownerRecord && method_exists($ownerRecord, 'messageTemplates')) {
                            $defaultTemplate = $ownerRecord->messageTemplates()->wherePivot('is_default', true)->first();
                            return $defaultTemplate ? $defaultTemplate->id : 'custom';
                        }
                        return 'custom';
                    })
                    ->reactive(),

                Toggle::make('use_whatsapp_web')
                    ->label('استخدام واتساب ويب (جديد)')
                    ->default(true)
                    ->reactive()
                    ->helperText('استخدم الخدمة الجديدة لواتساب ويب بدلاً من API القديم'),

                Textarea::make('message')
                    ->hint('يمكنك استخدام المتغيرات التالية: {student_name}, {group_name}, {curr_date}, {last_presence}')
                    ->default('السلام عليكم ورحمة الله وبركاته، {student_name} تذكير بالواجب المقرر اليوم، بارك الله فيكم.')
                    ->label('الرسالة')
                    ->required()
                    ->rows(4)
                    ->hidden(fn(Get $get) => $get('template_id') !== 'custom'),
            ])
            ->action(function (array $data) {
                $this->sendReminderToUnmarkedStudents($data);
            });
    }

    /**
     * Send reminder messages to students who haven't been marked as absent or present today
     */
    protected function sendReminderToUnmarkedStudents(array $data): void
    {
        $ownerRecord = $this->getLivewire()->ownerRecord ?? $this->getRecord();
        $today = now()->format('Y-m-d');

        // Get students who have no progress record for today (not marked as absent or present)
        $unmarkedStudents = $this->getUnmarkedStudents($ownerRecord, $today);

        if ($unmarkedStudents->isEmpty()) {
            Notification::make()
                ->title('جميع الطلاب مسجلين')
                ->body('جميع الطلاب لديهم سجلات حضور أو غياب لليوم')
                ->info()
                ->send();
            return;
        }

        // Get the message content
        $messageTemplate = $this->getMessageTemplate($data, $ownerRecord);

        if ($data['use_whatsapp_web'] ?? false) {
            $this->sendReminderViaWhatsAppWeb($unmarkedStudents, $messageTemplate, $ownerRecord);
        } else {
            $this->sendReminderViaLegacyWhatsApp($unmarkedStudents, $messageTemplate, $ownerRecord);
        }
    }

    /**
     * Get unmarked students for today (those without any progress record)
     */
    protected function getUnmarkedStudents($ownerRecord, string $today): Collection
    {
        if (!$ownerRecord || !method_exists($ownerRecord, 'students')) {
            return collect();
        }

        return $ownerRecord->students->filter(function ($student) use ($today) {
            return $student->progresses->where('date', $today)->count() == 0;
        });
    }

    /**
     * Get the message template content
     */
    protected function getMessageTemplate(array $data, $ownerRecord): string
    {
        if ($data['template_id'] === 'custom') {
            return $data['message'];
        }

        $template = GroupMessageTemplate::find($data['template_id']);
        if ($template) {
            return $template->content;
        }

        return $data['message'] ?? 'السلام عليكم، تذكير بالواجب المقرر اليوم، بارك الله فيكم.';
    }

    /**
     * Send reminder messages via WhatsApp Web (new service with defer)
     */
    protected function sendReminderViaWhatsAppWeb(Collection $students, string $messageTemplate, $ownerRecord): void
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

        foreach ($students as $student) {
            // Process message template with variables
            $processedMessage = Core::processMessageTemplate($messageTemplate, $student, $ownerRecord);

            // Clean phone number using helper
            $phoneNumber = PhoneHelper::cleanPhoneNumber($student->phone);

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

                // Use defer() to send the message asynchronously with minimal rate limiting
                defer(function () use ($session, $phoneNumber, $processedMessage, $messageHistory, $messageIndex) {
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
                            $processedMessage
                        );

                        // Update message history as sent
                        $messageHistory->update([
                            'status' => WhatsAppMessageStatus::SENT,
                            'whatsapp_message_id' => $result[0]['messageId'] ?? null,
                            'sent_at' => now(),
                        ]);

                        Log::info('WhatsApp reminder sent to unmarked student', [
                            'student_phone' => $phoneNumber,
                            'message_id' => $result[0]['messageId'] ?? null,
                            'message_index' => $messageIndex,
                            'delay_seconds' => $delaySeconds,
                        ]);

                    } catch (\Exception $e) {
                        // Update message history as failed
                        $messageHistory->update([
                            'status' => WhatsAppMessageStatus::FAILED,
                            'error_message' => $e->getMessage(),
                        ]);

                        Log::error('Failed to send WhatsApp reminder to unmarked student', [
                            'student_phone' => $phoneNumber,
                            'error' => $e->getMessage(),
                            'message_index' => $messageIndex,
                            'delay_seconds' => $delaySeconds,
                        ]);
                    }
                });

                $messagesQueued++;
                $messageIndex++;

            } catch (\Exception $e) {
                Log::error('Failed to queue WhatsApp reminder for unmarked student', [
                    'student_id' => $student->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Notification::make()
            ->title('تم جدولة التذكيرات للإرسال!')
            ->body("تم جدولة {$messagesQueued} تذكير لإرسالها للطلاب غير المسجلين")
            ->success()
            ->send();
    }

    /**
     * Send reminder messages via legacy WhatsApp service (for compatibility)
     */
    protected function sendReminderViaLegacyWhatsApp(Collection $students, string $messageTemplate, $ownerRecord): void
    {
        foreach ($students as $student) {
            $processedMessage = Core::processMessageTemplate($messageTemplate, $student, $ownerRecord);
            Core::sendSpecifMessageToStudent($student, $processedMessage);
        }

        Notification::make()
            ->title('تم إرسال التذكيرات!')
            ->body("تم إرسال التذكيرات لـ {$students->count()} طالب غير مسجل")
            ->success()
            ->send();
    }

}