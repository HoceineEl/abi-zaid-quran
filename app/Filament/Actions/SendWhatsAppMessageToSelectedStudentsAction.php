<?php

namespace App\Filament\Actions;

use App\Classes\Core;
use App\Enums\WhatsAppMessageStatus;
use App\Jobs\SendWhatsAppMessageJob;
use App\Models\Group;
use App\Models\GroupMessageTemplate;
use App\Models\Student;
use App\Models\WhatsAppMessageHistory;
use App\Models\WhatsAppSession;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\BulkAction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class SendWhatsAppMessageToSelectedStudentsAction extends BulkAction
{
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

                $showMessageField = $isAdmin || ! $defaultTemplate;

                if ($showMessageField) {
                    $defaultMessage = $defaultTemplate ? $defaultTemplate->content : 'السلام عليكم، نذكركم بالواجب المقرر اليوم، لعل المانع خير.';

                    $fields[] = Textarea::make('message')
                        ->hint('يمكنك استخدام المتغيرات التالية: {{student_name}}, {{group_name}}, {{curr_date}}, {{prefix}}, {{pronoun}}, {{verb}}')
                        ->default($defaultMessage)
                        ->label('الرسالة')
                        ->placeholder('اكتب رسالتك هنا...')
                        ->rows(8)
                        ->required()
                        ->hidden(fn (Get $get) => $isAdmin && $this->ownerRecord && $get('template_id') !== 'custom');
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

    protected function sendMessageToSelectedStudents(array $data, Collection $records): void
    {
        if ($records->isEmpty()) {
            Notification::make()
                ->title('لا يوجد طلاب محددين')
                ->warning()
                ->send();

            return;
        }

        $messageTemplate = $this->resolveMessageTemplate($data);

        $this->dispatchMessages($records, $messageTemplate);
    }

    protected function resolveMessageTemplate(array $data): string
    {
        if ($this->ownerRecord && ! auth()->user()->isAdministrator()) {
            $defaultTemplate = $this->ownerRecord->messageTemplates()->wherePivot('is_default', true)->first();
            if ($defaultTemplate) {
                return $defaultTemplate->content;
            }
        }

        if (($data['template_id'] ?? null) === 'custom') {
            return $data['message'];
        }

        if (isset($data['template_id'])) {
            $template = GroupMessageTemplate::find($data['template_id']);
            if ($template) {
                return $template->content;
            }
        }

        return $data['message'] ?? $data['message_content'] ?? 'السلام عليكم، نذكركم بالواجب المقرر اليوم، لعل المانع خير.';
    }

    protected function dispatchMessages(Collection $students, string $messageTemplate): void
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

        $messagesQueued = 0;
        $failedPhones = [];

        foreach ($students as $student) {
            if (! ($student instanceof Student)) {
                $student = Student::find($student);
                if (! $student) {
                    continue;
                }
            }

            $messageContent = $this->ownerRecord
                ? Core::processMessageTemplate($messageTemplate, $student, $this->ownerRecord)
                : $messageTemplate;

            $phoneNumber = $this->formatPhoneNumber($student->phone);

            if (! $phoneNumber) {
                $failedPhones[] = $student->name;

                continue;
            }

            try {
                WhatsAppMessageHistory::create([
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

                $delay = SendWhatsAppMessageJob::getStaggeredDelay($session->id);

                SendWhatsAppMessageJob::dispatch(
                    $session->id,
                    $phoneNumber,
                    $messageContent,
                    'text',
                    $student->id,
                    ['sender_user_id' => auth()->id()],
                )->delay(now()->addSeconds($delay));

                $messagesQueued++;
            } catch (\Exception $e) {
                Log::error('Failed to queue message for student', [
                    'student_id' => $student->id,
                    'error' => $e->getMessage(),
                ]);
                $failedPhones[] = $student->name;
            }
        }

        $notificationBody = "تم جدولة {$messagesQueued} رسالة لإرسالها للطلاب المحددين";

        if (! empty($failedPhones)) {
            $notificationBody .= "\n".'فشل الإرسال لـ: '.implode(', ', $failedPhones);
        }

        Notification::make()
            ->title('تم جدولة الرسائل للإرسال!')
            ->body($notificationBody)
            ->success()
            ->send();
    }

    protected function formatPhoneNumber(string $phone): ?string
    {
        try {
            return str_replace('+', '', phone($phone, 'MA')->formatE164());
        } catch (\Exception $e) {
            return null;
        }
    }
}
