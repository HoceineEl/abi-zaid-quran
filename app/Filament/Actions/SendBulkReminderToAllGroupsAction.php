<?php

namespace App\Filament\Actions;

use App\Classes\Core;
use App\Enums\WhatsAppMessageStatus;
use App\Helpers\PhoneHelper;
use App\Models\Group;
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
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class SendBulkReminderToAllGroupsAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'send_bulk_reminder_to_all_groups';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('تذكير')
            ->icon('heroicon-o-speaker-wave')
            ->color('warning')
            ->form([
                Forms\Components\Select::make('template_source')
                    ->label('مصدر القالب')
                    ->options([
                        'group_default' => 'استخدام القالب الافتراضي لكل مجموعة',
                        'global_template' => 'استخدام قالب موحد',
                        'custom' => 'رسالة مخصصة',
                    ])
                    ->default('group_default')
                    ->reactive()
                    ->helperText('اختر كيفية تحديد محتوى الرسالة لكل مجموعة'),

                Forms\Components\Select::make('global_template_id')
                    ->label('اختر القالب الموحد')
                    ->options(function () {
                        // Get all templates from all user's groups
                        $userGroups = $this->getUserGroups();
                        $templates = collect();

                        foreach ($userGroups as $group) {
                            $groupTemplates = $group->messageTemplates()->get();
                            foreach ($groupTemplates as $template) {
                                $templates->put($template->id, "{$template->name} (من مجموعة: {$group->name})");
                            }
                        }

                        return $templates->unique()->toArray();
                    })
                    ->visible(fn(Get $get) => $get('template_source') === 'global_template')
                    ->required(fn(Get $get) => $get('template_source') === 'global_template'),

                Toggle::make('use_whatsapp_web')
                    ->label('استخدام واتساب ويب (جديد)')
                    ->default(true)
                    ->reactive()
                    ->helperText('استخدم الخدمة الجديدة لواتساب ويب بدلاً من API القديم'),

                Textarea::make('message')
                    ->hint('يمكنك استخدام المتغيرات التالية: {student_name}, {group_name}, {curr_date}, {last_presence}')
                    ->default('السلام عليكم ورحمة الله وبركاته\n{student_name}، نذكركم بالواجب المقرر اليوم في مجموعة {group_name}، لعل المانع خير.\nبارك الله فيكم وزادكم حرصا.')
                    ->label('الرسالة')
                    ->required()
                    ->rows(4)
                    ->visible(fn(Get $get) => $get('template_source') === 'custom'),

                Forms\Components\Placeholder::make('groups_summary')
                    ->label('ملخص المجموعات')
                    ->content(function () {
                        $userGroups = $this->getUserGroups();
                        $groupCount = $userGroups->count();
                        $totalStudents = $userGroups->sum(fn($group) => $group->students->count());

                        return "سيتم إرسال التذكيرات لـ {$groupCount} مجموعة تحتوي على {$totalStudents} طالب إجمالي.";
                    }),
            ])
            ->action(function (array $data) {
                $this->sendBulkReminderToAllGroups($data);
            });
    }

    /**
     * Get user's groups (only groups they manage)
     */
    protected function getUserGroups(): EloquentCollection
    {
        return Group::whereHas('managers', function ($query) {
            $query->where('users.id', auth()->id());
        })->with(['students.progresses', 'messageTemplates'])->get();
    }

    /**
     * Send bulk reminder messages to all unmarked students across all user's groups
     */
    protected function sendBulkReminderToAllGroups(array $data): void
    {
        $today = now()->format('Y-m-d');
        $userGroups = $this->getUserGroups();

        if ($userGroups->isEmpty()) {
            Notification::make()
                ->title('لا توجد مجموعات')
                ->body('لا تملك صلاحية إدارة أي مجموعة')
                ->warning()
                ->send();
            return;
        }

        $totalUnmarkedStudents = 0;
        $groupsProcessed = 0;

        // Collect all unmarked students from all groups
        $allUnmarkedStudents = collect();

        foreach ($userGroups as $group) {
            $unmarkedStudents = $group->students->filter(function ($student) use ($today) {
                return $student->progresses->where('date', $today)->count() == 0;
            });

            if ($unmarkedStudents->isNotEmpty()) {
                foreach ($unmarkedStudents as $student) {
                    $allUnmarkedStudents->push([
                        'student' => $student,
                        'group' => $group,
                    ]);
                }
                $totalUnmarkedStudents += $unmarkedStudents->count();
                $groupsProcessed++;
            }
        }

        if ($totalUnmarkedStudents === 0) {
            Notification::make()
                ->title('جميع الطلاب مسجلين')
                ->body('جميع الطلاب في جميع المجموعات لديهم سجلات حضور أو غياب لليوم')
                ->info()
                ->send();
            return;
        }

        if ($data['use_whatsapp_web'] ?? false) {
            $this->sendBulkReminderViaWhatsAppWeb($allUnmarkedStudents, $data);
        } else {
            $this->sendBulkReminderViaLegacyWhatsApp($allUnmarkedStudents, $data);
        }

        Notification::make()
            ->title('تمت معالجة التذكيرات')
            ->body("تمت معالجة {$totalUnmarkedStudents} طالب من {$groupsProcessed} مجموعة")
            ->success()
            ->send();
    }

    /**
     * Get the message template content for a specific group
     */
    protected function getMessageTemplateForGroup(array $data, Group $group): string
    {
        switch ($data['template_source']) {
            case 'group_default':
                // Use group's default template
                $defaultTemplate = $group->messageTemplates()->wherePivot('is_default', true)->first();
                if ($defaultTemplate) {
                    return $defaultTemplate->content;
                }
                // Fallback to first template if no default
                $firstTemplate = $group->messageTemplates()->first();
                if ($firstTemplate) {
                    return $firstTemplate->content;
                }
                // Fallback to default message
                return 'السلام عليكم ورحمة الله وبركاته\n{student_name}، نذكركم بالواجب المقرر اليوم في مجموعة {group_name}، لعل المانع خير.\nبارك الله فيكم وزادكم حرصا.';

            case 'global_template':
                $template = GroupMessageTemplate::find($data['global_template_id']);
                if ($template) {
                    return $template->content;
                }
                return 'السلام عليكم ورحمة الله وبركاته\n{student_name}، نذكركم بالواجب المقرر اليوم في مجموعة {group_name}، لعل المانع خير.\nبارك الله فيكم وزادكم حرصا.';

            case 'custom':
                return $data['message'];

            default:
                return 'السلام عليكم ورحمة الله وبركاته\n{student_name}، نذكركم بالواجب المقرر اليوم في مجموعة {group_name}، لعل المانع خير.\nبارك الله فيكم وزادكم حرصا.';
        }
    }

    /**
     * Send bulk reminder messages via WhatsApp Web (new service with defer)
     */
    protected function sendBulkReminderViaWhatsAppWeb(Collection $studentsWithGroups, array $data): void
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

        foreach ($studentsWithGroups as $item) {
            $student = $item['student'];
            $group = $item['group'];

            // Get message template for this specific group
            $messageTemplate = $this->getMessageTemplateForGroup($data, $group);

            // Process message template with variables
            $processedMessage = Core::processMessageTemplate($messageTemplate, $student, $group);

            // Clean phone number using helper
            $phoneNumber = PhoneHelper::cleanPhoneNumber($student->phone);

            if (!$phoneNumber) {
                Log::warning('Invalid phone number for student in bulk reminder', [
                    'student_id' => $student->id,
                    'student_name' => $student->name,
                    'group_name' => $group->name,
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
                defer(function () use ($session, $phoneNumber, $processedMessage, $messageHistory, $student, $group, $messageIndex) {
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

                        Log::info('Bulk WhatsApp reminder sent to unmarked student', [
                            'student_id' => $student->id,
                            'student_name' => $student->name,
                            'group_name' => $group->name,
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

                        Log::error('Failed to send bulk WhatsApp reminder to unmarked student', [
                            'student_id' => $student->id,
                            'student_name' => $student->name,
                            'group_name' => $group->name,
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
                Log::error('Failed to queue bulk WhatsApp reminder for unmarked student', [
                    'student_id' => $student->id,
                    'student_name' => $student->name,
                    'group_name' => $group->name,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Notification::make()
            ->title('تم جدولة التذكيرات الجماعية للإرسال!')
            ->body("تم جدولة {$messagesQueued} تذكير لإرسالها للطلاب غير المسجلين في جميع المجموعات")
            ->success()
            ->send();
    }

    /**
     * Send bulk reminder messages via legacy WhatsApp service (for compatibility)
     */
    protected function sendBulkReminderViaLegacyWhatsApp(Collection $studentsWithGroups, array $data): void
    {
        foreach ($studentsWithGroups as $item) {
            $student = $item['student'];
            $group = $item['group'];

            // Get message template for this specific group
            $messageTemplate = $this->getMessageTemplateForGroup($data, $group);

            $processedMessage = Core::processMessageTemplate($messageTemplate, $student, $group);
            Core::sendSpecifMessageToStudent($student, $processedMessage);
        }

        Notification::make()
            ->title('تم إرسال التذكيرات الجماعية!')
            ->body("تم إرسال التذكيرات لـ {$studentsWithGroups->count()} طالب غير مسجل في جميع المجموعات")
            ->success()
            ->send();
    }

}