<?php

namespace App\Services;

use App\Classes\Core;
use App\Enums\MessageSubmissionType;
use App\Enums\WhatsAppMessageStatus;
use App\Helpers\PhoneHelper;
use App\Jobs\SendWhatsAppMessageJob;
use App\Models\Group;
use App\Models\GroupMessageTemplate;
use App\Models\Progress;
use App\Models\Student;
use App\Models\WhatsAppMessageHistory;
use App\Models\WhatsAppSession;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WhatsAppAttendanceService
{
    /**
     * @var array<string, array<string, mixed>>
     */
    protected array $groupPreviewCache = [];

    public function buildGroupPreview(Group $group, string $date): array
    {
        $cacheKey = $group->getKey().'|'.$date;

        if (isset($this->groupPreviewCache[$cacheKey])) {
            return $this->groupPreviewCache[$cacheKey];
        }

        $group->loadMissing([
            'students.progresses' => fn ($query) => $query
                ->where('date', $date)
                ->select(['id', 'student_id', 'date', 'status', 'with_reason', 'comment']),
        ]);

        $students = $group->students->sortBy('order_no')->values();
        $basePreview = [
            'group_id' => $group->id,
            'group_name' => $group->name,
            'submission_type' => ($group->message_submission_type ?? MessageSubmissionType::Media)->value,
            'submission_type_label' => ($group->message_submission_type ?? MessageSubmissionType::Media)->getLabel(),
            'status' => 'ready',
            'skip_reason' => null,
            'total_students' => $students->count(),
            'matched_student_ids' => [],
            'matched_student_names' => [],
            'already_present_ids' => [],
            'already_present_names' => [],
            'to_mark_present_ids' => [],
            'to_mark_present_names' => [],
            'existing_progress_student_ids' => [],
            'remaining_student_ids' => [],
            'remaining_student_names' => [],
            'remaining_with_valid_phone_count' => 0,
            'remaining_invalid_phone_count' => 0,
        ];

        if (! $group->whatsapp_group_jid) {
            return $this->groupPreviewCache[$cacheKey] = [
                ...$basePreview,
                'status' => 'skipped',
                'skip_reason' => 'لا توجد مجموعة واتساب مرتبطة',
            ];
        }

        if ($students->isEmpty()) {
            return $this->groupPreviewCache[$cacheKey] = [
                ...$basePreview,
                'status' => 'skipped',
                'skip_reason' => 'لا يوجد طلاب في المجموعة',
            ];
        }

        try {
            $senderPhones = $this->fetchWhatsAppSenders($group, $date);
        } catch (\Throwable $exception) {
            Log::error('Failed to build WhatsApp attendance preview', [
                'group_id' => $group->id,
                'date' => $date,
                'error' => $exception->getMessage(),
            ]);

            return $this->groupPreviewCache[$cacheKey] = [
                ...$basePreview,
                'status' => 'skipped',
                'skip_reason' => 'تعذر جلب رسائل واتساب لهذه المجموعة',
            ];
        }

        $senderIndex = PhoneHelper::buildSuffixIndex($senderPhones);
        $existingProgressByStudentId = $students
            ->mapWithKeys(function (Student $student): array {
                $progress = $student->progresses->first();

                return $progress ? [$student->id => $progress] : [];
            });

        $existingProgressIds = $existingProgressByStudentId->keys()->map(fn ($id) => (int) $id)->values();
        $alreadyPresentIds = $existingProgressByStudentId
            ->filter(fn (Progress $progress) => $progress->status === 'memorized')
            ->keys()
            ->map(fn ($id) => (int) $id)
            ->values();

        $matchedStudents = $students
            ->filter(fn (Student $student) => PhoneHelper::matchesAny($student->phone, $senderIndex))
            ->values();

        $toMarkPresentStudents = $matchedStudents
            ->reject(fn (Student $student) => $alreadyPresentIds->contains($student->id))
            ->values();

        $remainingStudents = $students
            ->reject(fn (Student $student) => $toMarkPresentStudents->contains('id', $student->id))
            ->reject(fn (Student $student) => $existingProgressIds->contains($student->id))
            ->values();

        return $this->groupPreviewCache[$cacheKey] = [
            ...$basePreview,
            'matched_student_ids' => $matchedStudents->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            'matched_student_names' => $matchedStudents->pluck('name')->values()->all(),
            'already_present_ids' => $alreadyPresentIds->all(),
            'already_present_names' => $students
                ->whereIn('id', $alreadyPresentIds->all())
                ->pluck('name')
                ->values()
                ->all(),
            'to_mark_present_ids' => $toMarkPresentStudents->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            'to_mark_present_names' => $toMarkPresentStudents->pluck('name')->values()->all(),
            'existing_progress_student_ids' => $existingProgressIds->all(),
            'remaining_student_ids' => $remainingStudents->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            'remaining_student_names' => $remainingStudents->pluck('name')->values()->all(),
            'remaining_with_valid_phone_count' => $remainingStudents
                ->filter(fn (Student $student) => filled(PhoneHelper::cleanPhoneNumber($student->phone)))
                ->count(),
            'remaining_invalid_phone_count' => $remainingStudents
                ->filter(fn (Student $student) => blank(PhoneHelper::cleanPhoneNumber($student->phone)))
                ->count(),
        ];
    }

    /**
     * @param  iterable<Group>  $groups
     * @return array<string, mixed>
     */
    public function buildBulkPreview(iterable $groups, string $date, bool $markOthersAbsent = false, bool $remindRemainingStudents = false): array
    {
        $groupPreviews = collect($groups)
            ->map(fn (Group $group) => $this->buildGroupPreview($group, $date))
            ->sortBy('group_name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        $readyGroups = $groupPreviews->where('status', 'ready')->values();
        $skippedGroups = $groupPreviews->where('status', '!=', 'ready')->values();

        $plannedRemainingCount = $markOthersAbsent
            ? 0
            : $readyGroups->sum(fn (array $preview) => count($preview['remaining_student_ids']));

        $plannedReminderCount = ($remindRemainingStudents && ! $markOthersAbsent)
            ? $readyGroups->sum('remaining_with_valid_phone_count')
            : 0;

        $plannedReminderInvalidPhoneCount = ($remindRemainingStudents && ! $markOthersAbsent)
            ? $readyGroups->sum('remaining_invalid_phone_count')
            : 0;

        return [
            'date' => $date,
            'groups' => $groupPreviews->all(),
            'totals' => [
                'group_count' => $groupPreviews->count(),
                'ready_group_count' => $readyGroups->count(),
                'skipped_group_count' => $skippedGroups->count(),
                'total_students' => $readyGroups->sum('total_students'),
                'matched_students' => $readyGroups->sum(fn (array $preview) => count($preview['matched_student_ids'])),
                'already_present_students' => $readyGroups->sum(fn (array $preview) => count($preview['already_present_ids'])),
                'to_mark_present_students' => $readyGroups->sum(fn (array $preview) => count($preview['to_mark_present_ids'])),
                'remaining_students' => $plannedRemainingCount,
                'planned_absent_students' => $markOthersAbsent
                    ? $readyGroups->sum(fn (array $preview) => count($preview['remaining_student_ids']))
                    : 0,
                'planned_reminders' => $plannedReminderCount,
                'planned_invalid_reminder_phones' => $plannedReminderInvalidPhoneCount,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $preview
     * @return array<string, mixed>
     */
    public function applyBulkPreview(array $preview, bool $markOthersAbsent = false, bool $remindRemainingStudents = false): array
    {
        $date = $preview['date'];
        $session = $this->resolveConnectedSession();

        $result = [
            'groups_processed' => 0,
            'groups_skipped' => 0,
            'students_marked_present' => 0,
            'students_marked_absent' => 0,
            'reminders_queued' => 0,
            'invalid_reminder_phones' => 0,
            'reminder_failures' => 0,
            'errors' => [],
            'groups' => [],
        ];

        foreach ($preview['groups'] as $groupPreview) {
            if (($groupPreview['status'] ?? 'skipped') !== 'ready') {
                $result['groups_skipped']++;
                $result['groups'][] = [
                    'group_name' => $groupPreview['group_name'],
                    'status' => 'skipped',
                    'skip_reason' => $groupPreview['skip_reason'],
                ];

                continue;
            }

            try {
                $group = Group::query()
                    ->with(['students.progresses' => fn ($query) => $query
                        ->where('date', $date)
                        ->select(['id', 'student_id', 'date', 'status', 'with_reason', 'comment'])])
                    ->findOrFail($groupPreview['group_id']);

                $applyResult = $this->applyGroupPreview(
                    $group,
                    $groupPreview,
                    $date,
                    $markOthersAbsent,
                    $remindRemainingStudents,
                    $session,
                );

                $result['groups_processed']++;
                $result['students_marked_present'] += $applyResult['students_marked_present'];
                $result['students_marked_absent'] += $applyResult['students_marked_absent'];
                $result['reminders_queued'] += $applyResult['reminders_queued'];
                $result['invalid_reminder_phones'] += $applyResult['invalid_reminder_phones'];
                $result['reminder_failures'] += $applyResult['reminder_failures'];
                $result['groups'][] = $applyResult;
            } catch (\Throwable $exception) {
                Log::error('Bulk WhatsApp attendance apply failed for group', [
                    'group_id' => $groupPreview['group_id'],
                    'date' => $date,
                    'error' => $exception->getMessage(),
                ]);

                $result['groups_skipped']++;
                $result['errors'][] = "{$groupPreview['group_name']}: {$exception->getMessage()}";
                $result['groups'][] = [
                    'group_name' => $groupPreview['group_name'],
                    'status' => 'error',
                    'skip_reason' => $exception->getMessage(),
                ];
            }
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $groupPreview
     * @return array<string, mixed>
     */
    public function applyGroupPreview(
        Group $group,
        array $groupPreview,
        string $date,
        bool $markOthersAbsent = false,
        bool $remindRemainingStudents = false,
        ?WhatsAppSession $session = null,
    ): array {
        $session ??= $this->resolveConnectedSession();
        $selectedStudentIds = collect($groupPreview['to_mark_present_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $attendanceResult = DB::transaction(function () use ($group, $date, $selectedStudentIds, $markOthersAbsent): array {
            $students = $group->students;
            $existingProgress = $students
                ->flatMap->progresses
                ->keyBy('student_id');

            $studentsMarkedPresent = 0;

            foreach ($selectedStudentIds as $studentId) {
                /** @var Progress|null $progress */
                $progress = $existingProgress->get($studentId);

                if ($progress?->status === 'memorized') {
                    continue;
                }

                if ($progress) {
                    $progress->update([
                        'status' => 'memorized',
                        'comment' => 'whatsapp_auto_attendance',
                    ]);
                } else {
                    $student = $students->firstWhere('id', $studentId);

                    if (! $student) {
                        continue;
                    }

                    $student->progresses()->create([
                        'date' => $date,
                        'status' => 'memorized',
                        'comment' => 'whatsapp_auto_attendance',
                    ]);
                }

                $studentsMarkedPresent++;
            }

            $studentsMarkedAbsent = 0;

            if ($markOthersAbsent) {
                $otherStudents = $students->reject(fn (Student $student) => $selectedStudentIds->contains($student->id));

                foreach ($otherStudents as $student) {
                    /** @var Progress|null $progress */
                    $progress = $existingProgress->get($student->id);

                    if ($progress?->status === 'memorized') {
                        continue;
                    }

                    if ($progress) {
                        $progress->update([
                            'status' => 'absent',
                            'with_reason' => false,
                            'comment' => null,
                        ]);
                    } else {
                        $student->progresses()->create([
                            'date' => $date,
                            'status' => 'absent',
                            'with_reason' => false,
                            'comment' => null,
                            'page_id' => null,
                            'lines_from' => null,
                            'lines_to' => null,
                        ]);
                    }

                    $studentsMarkedAbsent++;
                }
            }

            return [
                'students_marked_present' => $studentsMarkedPresent,
                'students_marked_absent' => $studentsMarkedAbsent,
            ];
        });

        $group->unsetRelation('students');
        $group->load([
            'students.progresses' => fn ($query) => $query
                ->where('date', $date)
                ->select(['id', 'student_id', 'date', 'status', 'with_reason', 'comment']),
            'messageTemplates',
        ]);

        $remainingStudents = $this->getRemainingStudents($group, $date);
        $reminderResult = $remindRemainingStudents
            ? $this->queueRemindersForRemainingStudents($group, $remainingStudents, $session)
            : [
                'reminders_queued' => 0,
                'invalid_reminder_phones' => 0,
                'reminder_failures' => 0,
            ];

        return [
            'group_id' => $group->id,
            'group_name' => $group->name,
            'status' => 'processed',
            ...$attendanceResult,
            ...$reminderResult,
            'remaining_students' => $remainingStudents->count(),
        ];
    }

    /**
     * @return string[]
     */
    public function fetchWhatsAppSenders(Group $group, string $date): array
    {
        if (! $group->whatsapp_group_jid) {
            return [];
        }

        $session = $this->resolveConnectedSession();
        $submissionType = $group->message_submission_type ?? MessageSubmissionType::Media;

        return app(WhatsAppService::class)->getGroupAttendeesForDate(
            $session->name,
            $group->whatsapp_group_jid,
            $date,
            $submissionType->whatsappMessageTypes(),
        );
    }

    public function getDefaultReminderMessage(): string
    {
        return <<<'ARABIC'
السلام عليكم ورحمة الله وبركاته
*أخي الطالب {student_name}*،
نذكرك بالواجب المقرر اليوم، لعل المانع خير.
بارك الله في وقتك وجهدك وزادك علماً ونفعاً. 🤲
ARABIC;
    }

    protected function getRemainingStudents(Group $group, string $date): Collection
    {
        return $group->students
            ->filter(fn (Student $student) => $student->progresses->isEmpty())
            ->values();
    }

    /**
     * @param  Collection<int, Student>  $students
     * @return array<string, int>
     */
    protected function queueRemindersForRemainingStudents(Group $group, Collection $students, WhatsAppSession $session): array
    {
        if ($students->isEmpty()) {
            return [
                'reminders_queued' => 0,
                'invalid_reminder_phones' => 0,
                'reminder_failures' => 0,
            ];
        }

        $messageTemplate = $this->resolveReminderTemplateForGroup($group);
        $remindersQueued = 0;
        $invalidReminderPhones = 0;
        $reminderFailures = 0;

        foreach ($students as $student) {
            $phoneNumber = PhoneHelper::cleanPhoneNumber($student->phone);

            if (! $phoneNumber) {
                Log::warning('Invalid phone number for WhatsApp attendance reminder', [
                    'group_id' => $group->id,
                    'student_id' => $student->id,
                    'phone' => $student->phone,
                ]);
                $invalidReminderPhones++;

                continue;
            }

            try {
                $processedMessage = Core::processMessageTemplate($messageTemplate, $student, $group);

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

                $remindersQueued++;
            } catch (\Throwable $exception) {
                Log::error('Failed to queue WhatsApp attendance reminder', [
                    'group_id' => $group->id,
                    'student_id' => $student->id,
                    'error' => $exception->getMessage(),
                ]);
                $reminderFailures++;
            }
        }

        return [
            'reminders_queued' => $remindersQueued,
            'invalid_reminder_phones' => $invalidReminderPhones,
            'reminder_failures' => $reminderFailures,
        ];
    }

    protected function resolveReminderTemplateForGroup(Group $group): string
    {
        if ($group->relationLoaded('messageTemplates')) {
            /** @var Collection<int, GroupMessageTemplate> $loadedTemplates */
            $loadedTemplates = $group->messageTemplates;
            $defaultTemplate = $loadedTemplates->first(
                fn (GroupMessageTemplate $template) => (bool) $template->pivot?->is_default,
            );

            return $defaultTemplate?->content
                ?? $loadedTemplates->first()?->content
                ?? $this->getDefaultReminderMessage();
        }

        $defaultTemplate = $group->messageTemplates()->wherePivot('is_default', true)->first();

        if ($defaultTemplate) {
            return $defaultTemplate->content;
        }

        return $group->messageTemplates()->first()?->content ?? $this->getDefaultReminderMessage();
    }

    protected function resolveConnectedSession(): WhatsAppSession
    {
        $session = WhatsAppSession::getUserSession(auth()->id());

        if (! $session?->isConnected()) {
            throw new \RuntimeException('جلسة واتساب غير متصلة');
        }

        return $session;
    }
}
