<?php

namespace App\Filament\Actions;

use App\Classes\Core;
use App\Models\GroupMessageTemplate;
use App\Services\GroupWhatsAppAutomationService;
use Filament\Forms;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;
use Illuminate\Database\Eloquent\Collection;

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
                    // Use the simple default template if available
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
                        ->hidden(fn(Get $get) => $isAdmin && $get('template_id') !== 'custom');
                }

                return $fields;
            })
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
        $messagesQueued = $this->automation()->sendRemindersToUnmarkedStudents($ownerRecord, $today, $messageTemplate);

        if ($messagesQueued === 0) {
            Notification::make()
                ->title('تعذر إرسال التذكيرات')
                ->body('لا توجد جلسة واتساب متصلة للمشرف المحدد أو للمشرف العام.')
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title('تم جدولة التذكيرات للإرسال!')
            ->body("تم جدولة {$messagesQueued} تذكير لإرسالها للطلاب غير المسجلين")
            ->success()
            ->send();
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

        return $data['message'] ?? <<<'ARABIC'
السلام عليكم ورحمة الله وبركاته
*أخي الطالب {student_name}*،
نذكرك بالواجب المقرر اليوم، لعل المانع خير.
بارك الله في وقتك وجهدك وزادك علماً ونفعاً. 🤲
ARABIC;
    }

    /**
     * Send reminder messages via WhatsApp Web using queue jobs
     */
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

    protected function automation(): GroupWhatsAppAutomationService
    {
        return app(GroupWhatsAppAutomationService::class);
    }
}
