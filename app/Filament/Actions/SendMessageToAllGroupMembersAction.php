<?php

namespace App\Filament\Actions;

use App\Classes\Core;
use App\Enums\WhatsAppMessageStatus;
use App\Jobs\SendWhatsAppMessageJob;
use App\Models\Group;
use App\Models\GroupMessageTemplate;
use App\Models\WhatsAppMessageHistory;
use App\Models\WhatsAppSession;
use Filament\Forms;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class SendMessageToAllGroupMembersAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'send_message_to_all_group_members';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('رسالة عامة')
            ->icon('heroicon-o-megaphone')
            ->color('primary')
            ->form(function () {
                $fields = [];
                $isAdmin = auth()->user()->isAdministrator();

                if ($isAdmin) {
                    $fields[] = Forms\Components\Select::make('template_source')
                        ->label('مصدر القالب')
                        ->options([
                            'group_default' => 'استخدام القالب الافتراضي لكل مجموعة',
                            'global_template' => 'استخدام قالب موحد',
                            'custom' => 'رسالة مخصصة',
                        ])
                        ->default('group_default')
                        ->reactive()
                        ->helperText('اختر كيفية تحديد محتوى الرسالة لكل مجموعة');

                    $fields[] = Forms\Components\Select::make('global_template_id')
                        ->label('اختر القالب الموحد')
                        ->options(function () {
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
                        ->visible(fn (Get $get) => $get('template_source') === 'global_template')
                        ->required(fn (Get $get) => $get('template_source') === 'global_template');

                    $fields[] = Textarea::make('message')
                        ->hint('يمكنك استخدام المتغيرات التالية: {student_name}, {group_name}, {curr_date}, {last_presence}')
                        ->default('السلام عليكم ورحمة الله وبركاته\n{student_name}، رسالة عامة من مجموعة {group_name}.\nبارك الله فيكم وزادكم حرصا.')
                        ->label('الرسالة')
                        ->required()
                        ->rows(4)
                        ->visible(fn (Get $get) => $get('template_source') === 'custom');
                }

                $fields[] = Forms\Components\Placeholder::make('groups_summary')
                    ->label('ملخص المجموعات')
                    ->content(function () {
                        $userGroups = $this->getUserGroups();
                        $groupCount = $userGroups->count();
                        $totalStudents = $userGroups->sum(fn ($group) => $group->students->count());

                        return "سيتم إرسال الرسائل لجميع الطلاب في {$groupCount} مجموعة ({$totalStudents} طالب إجمالي).";
                    });

                return $fields;
            })
            ->action(function (array $data) {
                $this->sendMessageToAllGroupMembers($data);
            });
    }

    protected function getUserGroups(): EloquentCollection
    {
        return Group::whereHas('managers', function ($query) {
            $query->where('users.id', auth()->id());
        })->with(['students.progresses', 'messageTemplates'])->get();
    }

    protected function sendMessageToAllGroupMembers(array $data): void
    {
        $userGroups = $this->getUserGroups();

        if ($userGroups->isEmpty()) {
            Notification::make()
                ->title('لا توجد مجموعات')
                ->body('لا تملك صلاحية إدارة أي مجموعة')
                ->warning()
                ->send();

            return;
        }

        $allStudentsWithGroups = collect();
        $totalStudents = 0;
        $groupsProcessed = 0;

        foreach ($userGroups as $group) {
            $students = $group->students;

            if ($students->isNotEmpty()) {
                foreach ($students as $student) {
                    $allStudentsWithGroups->push([
                        'student' => $student,
                        'group' => $group,
                    ]);
                }
                $totalStudents += $students->count();
                $groupsProcessed++;
            }
        }

        if ($totalStudents === 0) {
            Notification::make()
                ->title('لا يوجد طلاب')
                ->body('لا يوجد طلاب في المجموعات التي تديرها')
                ->info()
                ->send();

            return;
        }

        $messagesQueued = $this->dispatchMessages($allStudentsWithGroups, $data);

        Notification::make()
            ->title('تمت معالجة الرسائل العامة')
            ->body("تم جدولة {$messagesQueued} رسالة من {$groupsProcessed} مجموعة")
            ->success()
            ->send();
    }

    protected function getMessageTemplateForGroup(array $data, Group $group): string
    {
        $isAdmin = auth()->user()->isAdministrator();

        if (! $isAdmin) {
            $defaultTemplate = $group->messageTemplates()->wherePivot('is_default', true)->first();
            if ($defaultTemplate) {
                return $defaultTemplate->content;
            }
            $firstTemplate = $group->messageTemplates()->first();
            if ($firstTemplate) {
                return $firstTemplate->content;
            }

            return 'السلام عليكم ورحمة الله وبركاته\n{student_name}، رسالة عامة من مجموعة {group_name}.\nبارك الله فيكم وزادكم حرصا.';
        }

        return match ($data['template_source'] ?? 'group_default') {
            'group_default' => $this->getGroupDefaultTemplate($group),
            'global_template' => GroupMessageTemplate::find($data['global_template_id'])?->content
                ?? 'السلام عليكم ورحمة الله وبركاته\n{student_name}، رسالة عامة من مجموعة {group_name}.\nبارك الله فيكم وزادكم حرصا.',
            'custom' => $data['message'],
            default => 'السلام عليكم ورحمة الله وبركاته\n{student_name}، رسالة عامة من مجموعة {group_name}.\nبارك الله فيكم وزادكم حرصا.',
        };
    }

    protected function getGroupDefaultTemplate(Group $group): string
    {
        $defaultTemplate = $group->messageTemplates()->wherePivot('is_default', true)->first();
        if ($defaultTemplate) {
            return $defaultTemplate->content;
        }
        $firstTemplate = $group->messageTemplates()->first();
        if ($firstTemplate) {
            return $firstTemplate->content;
        }

        return 'السلام عليكم ورحمة الله وبركاته\n{student_name}، رسالة عامة من مجموعة {group_name}.\nبارك الله فيكم وزادكم حرصا.';
    }

    protected function dispatchMessages(Collection $studentsWithGroups, array $data): int
    {
        $session = WhatsAppSession::getUserSession(auth()->id());

        if (! $session || ! $session->isConnected()) {
            Notification::make()
                ->title('جلسة واتساب غير متصلة')
                ->body('يرجى التأكد من أن لديك جلسة واتساب متصلة قبل إرسال الرسائل')
                ->danger()
                ->send();

            return 0;
        }

        $messagesQueued = 0;

        foreach ($studentsWithGroups as $item) {
            $student = $item['student'];
            $group = $item['group'];

            $messageTemplate = $this->getMessageTemplateForGroup($data, $group);
            $processedMessage = Core::processMessageTemplate($messageTemplate, $student, $group);

            try {
                $phoneNumber = str_replace('+', '', phone($student->phone, 'MA')->formatE164());
            } catch (\Exception $e) {
                $phoneNumber = null;
            }

            if (! $phoneNumber) {
                Log::warning('Invalid phone number for student in group message', [
                    'student_id' => $student->id,
                    'group_name' => $group->name,
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

                SendWhatsAppMessageJob::dispatch(
                    $session->id,
                    $phoneNumber,
                    $processedMessage,
                    'text',
                    $student->id,
                    ['sender_user_id' => auth()->id()],
                );

                $messagesQueued++;
            } catch (\Exception $e) {
                Log::error('Failed to queue group message for student', [
                    'student_id' => $student->id,
                    'group_name' => $group->name,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $messagesQueued;
    }
}
