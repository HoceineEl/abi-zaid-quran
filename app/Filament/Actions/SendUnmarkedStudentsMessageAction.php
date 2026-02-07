<?php

namespace App\Filament\Actions;

use App\Classes\Core;
use App\Enums\WhatsAppMessageStatus;
use App\Jobs\SendWhatsAppMessageJob;
use App\Models\GroupMessageTemplate;
use App\Models\Student;
use App\Models\WhatsAppMessageHistory;
use App\Models\WhatsAppSession;
use Filament\Forms;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class SendUnmarkedStudentsMessageAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'send_unmarked_students_message';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('إرسال رسالة تذكير للبقية')
            ->icon('heroicon-o-chat-bubble-oval-left')
            ->color('warning')
            ->form(function () {
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

                $fields[] = Toggle::make('with_reason')
                    ->label('غياب بعذر')
                    ->default(false)
                    ->helperText('هل تريد تسجيل الطلاب كغائبين بعذر؟');

                // Show message field for admins when custom is selected, or always for non-admins without default template
                $showMessageField = $isAdmin || !$defaultTemplate;

                if ($showMessageField) {
                    $defaultMessage = $defaultTemplate ? $defaultTemplate->content : 'السلام عليكم ورحمة الله وبركاته، {student_name} لم ترسل الواجب المقرر اليوم، لعل المانع خير.';

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
                $this->sendMessagesToUnmarkedStudents($data);
            });
    }

    /**
     * Send messages to unmarked students (those without progress records)
     */
    protected function sendMessagesToUnmarkedStudents(array $data): void
    {
        $ownerRecord = $this->getLivewire()->ownerRecord ?? $this->getRecord();
        $selectedDate = $this->getSelectedDate();

        // Get unmarked students
        $unmarkedStudents = $this->getUnmarkedStudents($ownerRecord, $selectedDate);

        if ($unmarkedStudents->isEmpty()) {
            Notification::make()
                ->title('لا يوجد طلاب غير مسجلين')
                ->body('جميع الطلاب لديهم سجلات حضور لهذا التاريخ')
                ->info()
                ->send();
            return;
        }

        // Mark students as absent first (this is part of the original logic)
        $withReason = $data['with_reason'] ?? false;
        $this->markStudentsAsAbsent($unmarkedStudents, $selectedDate, $withReason);

        // Get the message content
        $messageTemplate = $this->getMessageTemplate($data, $ownerRecord);

        // Only send messages if it's today's date and NOT marked with reason
        if ($selectedDate === now()->format('Y-m-d') && !$withReason) {
            $this->sendViaWhatsAppWeb($unmarkedStudents, $messageTemplate, $data, $ownerRecord);
        } else {
            $message = $withReason
                ? "تم تسجيل {$unmarkedStudents->count()} طالب كغائبين بعذر للتاريخ المحدد"
                : "تم تسجيل {$unmarkedStudents->count()} طالب كغائبين للتاريخ المحدد";

            Notification::make()
                ->title('تم تسجيل الطلاب كغائبين')
                ->body($message)
                ->success()
                ->send();
        }
    }

    /**
     * Get unmarked students for the selected date (those without any progress record)
     */
    protected function getUnmarkedStudents($ownerRecord, string $selectedDate): Collection
    {
        if (!$ownerRecord || !method_exists($ownerRecord, 'students')) {
            return collect();
        }

        return $ownerRecord->students->filter(function ($student) use ($selectedDate) {
            return $student->progresses->where('date', $selectedDate)->count() == 0 ||
                $student->progresses->where('date', $selectedDate)->where('status', null)->count() > 0;
        });
    }

    /**
     * Mark students as absent before sending messages
     */
    protected function markStudentsAsAbsent(Collection $students, string $selectedDate, bool $withReason): void
    {
        foreach ($students as $student) {
            if ($student->progresses->where('date', $selectedDate)->count() == 0) {
                // Create new progress record
                $student->progresses()->create([
                    'date' => $selectedDate,
                    'status' => 'absent',
                    'with_reason' => $withReason,
                    'comment' => null,
                    'page_id' => null,
                    'lines_from' => null,
                    'lines_to' => null,
                ]);
            } else {
                // Update existing progress record
                $student->progresses()
                    ->where('date', $selectedDate)
                    ->update([
                        'status' => 'absent',
                        'with_reason' => $withReason,
                        'comment' => null,
                    ]);
            }
        }
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

        return $data['message'] ?? 'السلام عليكم، لم ترسل الواجب المقرر اليوم، لعل المانع خير.';
    }

    /**
     * Send messages via WhatsApp Web using queue jobs
     */
    protected function sendViaWhatsAppWeb(Collection $students, string $messageTemplate, array $data, $ownerRecord): void
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

        foreach ($students as $student) {
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
                WhatsAppMessageHistory::create([
                    'session_id' => $session->id,
                    'sender_user_id' => auth()->id(),
                    'recipient_phone' => $phoneNumber,
                    'message_type' => 'text',
                    'message_content' => $processedMessage,
                    'status' => WhatsAppMessageStatus::QUEUED,
                ]);

                // Dispatch the job to send the message with rate limiting
                SendWhatsAppMessageJob::dispatch(
                    $session->id,
                    $phoneNumber,
                    $processedMessage,
                    'text',
                    $student->id,
                    ['sender_user_id' => auth()->id()]
                );

                $messagesQueued++;
            } catch (\Exception $e) {
                Log::error('Failed to queue WhatsApp message for unmarked student', [
                    'student_id' => $student->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Notification::make()
            ->title('تم جدولة الرسائل للإرسال!')
            ->body("تم تسجيل {$students->count()} طالب كغائبين وجدولة {$messagesQueued} رسالة للإرسال")
            ->success()
            ->send();
    }

    /**
     * Send messages via legacy WhatsApp service (for compatibility)
     */
    protected function sendViaLegacyWhatsApp(Collection $students, string $messageTemplate, $ownerRecord): void
    {
        foreach ($students as $student) {
            $processedMessage = Core::processMessageTemplate($messageTemplate, $student, $ownerRecord);
            Core::sendSpecifMessageToStudent($student, $processedMessage);
        }

        Notification::make()
            ->title('تم إرسال الرسائل!')
            ->body("تم تسجيل {$students->count()} طالب كغائبين وإرسال الرسائل لهم")
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
