<?php

namespace App\Filament\Actions;

use App\Classes\Core;
use App\Enums\WhatsAppMessageStatus;
use App\Jobs\SendWhatsAppMessageJob;
use App\Models\GroupMessageTemplate;
use App\Models\WhatsAppMessageHistory;
use App\Models\WhatsAppSession;
use Filament\Forms;
use Filament\Forms\Components\Textarea;
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
            ->form(function () {
                $fields = [];
                $isAdmin = auth()->user()->isAdministrator();
                $ownerRecord = $this->getLivewire()->ownerRecord ?? $this->getRecord();
                $defaultTemplate = null;

                if ($ownerRecord && method_exists($ownerRecord, 'messageTemplates')) {
                    $defaultTemplate = $ownerRecord->messageTemplates()->wherePivot('is_default', true)->first();
                }

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

                $showMessageField = $isAdmin || ! $defaultTemplate;

                if ($showMessageField) {
                    $defaultMessage = $defaultTemplate ? $defaultTemplate->content : 'السلام عليكم ورحمة الله وبركاته، {student_name} لم تنس واجب اليوم، لعل المانع خير.';

                    $fields[] = Textarea::make('message')
                        ->hint('يمكنك استخدام المتغيرات التالية: {student_name}, {group_name}, {curr_date}, {last_presence}')
                        ->default($defaultMessage)
                        ->label('الرسالة')
                        ->required()
                        ->rows(4)
                        ->hidden(fn (Get $get) => $isAdmin && $get('template_id') !== 'custom');
                }

                return $fields;
            })
            ->action(function (array $data) {
                $this->sendMessagesToAbsentStudents($data);
            });
    }

    protected function sendMessagesToAbsentStudents(array $data): void
    {
        $ownerRecord = $this->getLivewire()->ownerRecord ?? $this->getRecord();
        $selectedDate = $this->getSelectedDate();

        $absentStudents = $this->getAbsentStudents($ownerRecord, $selectedDate);

        if ($absentStudents->isEmpty()) {
            Notification::make()
                ->title('لا يوجد طلاب غائبين')
                ->body('لم يتم العثور على طلاب غائبين في التاريخ المحدد')
                ->warning()
                ->send();

            return;
        }

        $messageTemplate = $this->resolveMessageTemplate($data, $ownerRecord);

        $this->dispatchMessages($absentStudents, $messageTemplate, $ownerRecord);
    }

    protected function getAbsentStudents($ownerRecord, string $selectedDate): Collection
    {
        if (! $ownerRecord || ! method_exists($ownerRecord, 'students')) {
            return new Collection;
        }

        return $ownerRecord->students->filter(function ($student) use ($selectedDate) {
            return $student->progresses
                ->where('date', $selectedDate)
                ->where('status', 'absent')
                ->where('with_reason', false)
                ->count() > 0;
        });
    }

    protected function getSelectedDate(): string
    {
        $livewire = $this->getLivewire();
        if (method_exists($livewire, 'getTableFilters') && isset($livewire->tableFilters['date']['value'])) {
            return $livewire->tableFilters['date']['value'];
        }

        return now()->format('Y-m-d');
    }

    protected function resolveMessageTemplate(array $data, $ownerRecord): string
    {
        if (! auth()->user()->isAdministrator() && $ownerRecord && method_exists($ownerRecord, 'messageTemplates')) {
            $defaultTemplate = $ownerRecord->messageTemplates()->wherePivot('is_default', true)->first();
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

        return $data['message'] ?? 'السلام عليكم، لم تنس واجب اليوم، لعل المانع خير.';
    }

    protected function dispatchMessages(Collection $absentStudents, string $messageTemplate, $ownerRecord): void
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

        foreach ($absentStudents as $student) {
            $processedMessage = Core::processMessageTemplate($messageTemplate, $student, $ownerRecord);
            $phoneNumber = $this->formatPhoneNumber($student->phone);

            if (! $phoneNumber) {
                Log::warning('Invalid phone number for student', [
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

                $delay = SendWhatsAppMessageJob::getStaggeredDelay($session->id);

                SendWhatsAppMessageJob::dispatch(
                    $session->id,
                    $phoneNumber,
                    $processedMessage,
                    'text',
                    $student->id,
                    ['sender_user_id' => auth()->id()],
                )->delay(now()->addSeconds($delay));

                $messagesQueued++;
            } catch (\Exception $e) {
                Log::error('Failed to queue message for absent student', [
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

    protected function formatPhoneNumber(string $phone): ?string
    {
        try {
            return str_replace('+', '', phone($phone, 'MA')->formatE164());
        } catch (\Exception $e) {
            return null;
        }
    }
}
