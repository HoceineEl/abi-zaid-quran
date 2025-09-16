<?php

namespace App\Filament\Actions;

use App\Classes\Core;
use App\Enums\WhatsAppMessageStatus;
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

class SendAbsentStudentsMessageAction extends Action
{
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
                    ->default('السلام عليكم ورحمة الله وبركاته، {student_name} لم تنس واجب اليوم، لعل المانع خير.')
                    ->label('الرسالة')
                    ->required()
                    ->rows(4)
                    ->hidden(fn(Get $get) => $get('template_id') !== 'custom'),
            ])
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

        if ($data['use_whatsapp_web'] ?? false) {
            $this->sendViaWhatsAppWeb($absentStudents, $messageTemplate, $data, $ownerRecord);
        } else {
            $this->sendViaLegacyWhatsApp($absentStudents, $messageTemplate, $ownerRecord);
        }
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
            return $student->progresses->where('date', $selectedDate)->where('status', 'absent')->count() > 0;
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
        if ($data['template_id'] === 'custom') {
            return $data['message'];
        }

        $template = GroupMessageTemplate::find($data['template_id']);
        if ($template) {
            return $template->content;
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

        $messagesSent = 0;
        $messagesQueued = 0;

        foreach ($absentStudents as $student) {
            // Process message template with variables
            $processedMessage = Core::processMessageTemplate($messageTemplate, $student, $ownerRecord);

            // Clean phone number
            $phoneNumber = $this->cleanPhoneNumber($student->phone);

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

                // Use defer() to send the message asynchronously
                defer(function () use ($session, $phoneNumber, $processedMessage, $messageHistory) {
                    try {
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

                        Log::info('WhatsApp message sent to absent student', [
                            'student_phone' => $phoneNumber,
                            'message_id' => $result[0]['messageId'] ?? null,
                        ]);

                    } catch (\Exception $e) {
                        // Update message history as failed
                        $messageHistory->update([
                            'status' => WhatsAppMessageStatus::FAILED,
                            'error_message' => $e->getMessage(),
                        ]);

                        Log::error('Failed to send WhatsApp message to absent student', [
                            'student_phone' => $phoneNumber,
                            'error' => $e->getMessage(),
                        ]);
                    }
                });

                $messagesQueued++;

            } catch (\Exception $e) {
                Log::error('Failed to queue WhatsApp message for absent student', [
                    'student_id' => $student->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

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

    /**
     * Clean and format phone number for WhatsApp
     */
    protected function cleanPhoneNumber(string $phone): ?string
    {
        // Remove any spaces, dashes or special characters
        $number = preg_replace('/[^0-9]/', '', $phone);

        // Handle different Moroccan number formats
        if (strlen($number) === 9 && in_array(substr($number, 0, 1), ['6', '7'])) {
            // If number starts with 6 or 7 and is 9 digits
            return '212' . $number;
        } elseif (strlen($number) === 10 && in_array(substr($number, 0, 2), ['06', '07'])) {
            // If number starts with 06 or 07 and is 10 digits
            return '212' . substr($number, 1);
        } elseif (strlen($number) === 12 && substr($number, 0, 3) === '212') {
            // If number already has 212 country code
            return $number;
        }

        // Return null for invalid numbers
        return null;
    }
}