<?php

namespace App\Services;

use App\Classes\Core;
use App\Enums\MessageSubmissionType;
use App\Enums\WhatsAppMessageStatus;
use App\Helpers\PhoneHelper;
use App\Jobs\SendWhatsAppMessageJob;
use App\Models\Group;
use App\Models\Progress;
use App\Models\Student;
use App\Models\WhatsAppMessageHistory;
use App\Models\WhatsAppSession;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class GroupWhatsAppAutomationService
{
    public const EVENING_REMINDER_PASS = 'evening_reminder_pass';
    public const CLOSE_PASS = 'close_pass';

    private const DEFAULT_REMINDER_TEMPLATE = <<<'ARABIC'
السلام عليكم ورحمة الله وبركاته
*أخي الطالب {student_name}*،
هذا تذكير  بواجب اليوم في مجموعة {group_name}.
نسأل الله أن يفتح عليك وأن يبارك في وقتك، فلا تنس إرسال الواجب قبل إغلاق الحلقة.
ARABIC;

    public function __construct(
        private readonly GroupWhatsAppSessionResolver $sessionResolver,
        private readonly WhatsAppService $whatsAppService,
    ) {}

    public function hasAvailableSession(Group $group): bool
    {
        return $this->sessionResolver->hasAvailableSession($group);
    }

    public function previewMatches(Group $group, string $date): array
    {
        $students = $group->students()->orderBy('order_no')->get();
        $senderPhones = $this->fetchWhatsAppSenders($group, $date);
        $senderIndex = PhoneHelper::buildSuffixIndex($senderPhones);

        $existingPresentIds = $group->progresses()
            ->where('date', $date)
            ->where('status', 'memorized')
            ->pluck('student_id')
            ->flip()
            ->all();

        $matchedStudentIds = [];
        $alreadyPresentIds = [];
        $descriptions = [];

        foreach ($students as $student) {
            $phone = $student->phone;

            if (isset($existingPresentIds[$student->id])) {
                $alreadyPresentIds[] = $student->id;
                $matchedStudentIds[] = $student->id;
                $descriptions[$student->id] = "{$phone} — مسجل مسبقاً";

                continue;
            }

            if (PhoneHelper::matchesAny($phone, $senderIndex)) {
                $matchedStudentIds[] = $student->id;
                $descriptions[$student->id] = "{$phone} — تم المطابقة من واتساب";
            } else {
                $descriptions[$student->id] = $phone;
            }
        }

        return [
            'matched_student_ids' => $matchedStudentIds,
            'already_present_ids' => $alreadyPresentIds,
            'descriptions' => $descriptions,
            'matched_count' => count($matchedStudentIds),
            'total_students' => $students->count(),
            'session' => $this->sessionResolver->resolveForGroup($group),
        ];
    }

    public function markAttendance(Group $group, string $date, array $studentIds, string $comment = 'whatsapp_auto_attendance'): int
    {
        if ($studentIds === []) {
            return 0;
        }

        $session = $this->sessionResolver->resolveForGroup($group);

        $existingProgress = $group->progresses()
            ->where('date', $date)
            ->whereIn('student_id', $studentIds)
            ->get()
            ->keyBy('student_id');

        $createdCount = 0;
        $attendanceData = [
            'status' => 'memorized',
            'comment' => $comment,
        ];

        foreach ($studentIds as $studentId) {
            /** @var Progress|null $progress */
            $progress = $existingProgress->get($studentId);

            if ($progress?->status === 'memorized') {
                continue;
            }

            if ($progress) {
                $progress->update($attendanceData);
            } else {
                $group->students()->find($studentId)?->progresses()->create([
                    'created_by' => $session?->user_id,
                    'date' => $date,
                    ...$attendanceData,
                ]);
            }

            $createdCount++;
        }

        return $createdCount;
    }

    public function sendRemindersToUnmarkedStudents(Group $group, string $date, ?string $messageTemplate = null): int
    {
        $session = $this->sessionResolver->resolveForGroup($group);

        if (! $session?->isConnected()) {
            return 0;
        }

        $students = $this->getUnmarkedStudents($group, $date);

        if ($students->isEmpty()) {
            return 0;
        }

        $messageTemplate ??= $this->resolveReminderTemplate($group);
        $messagesQueued = 0;

        foreach ($students as $student) {
            $processedMessage = Core::processMessageTemplate($messageTemplate, $student, $group);
            $phoneNumber = PhoneHelper::cleanPhoneNumber($student->phone);

            if (! $phoneNumber) {
                Log::warning('Invalid phone number for student reminder', [
                    'group_id' => $group->id,
                    'student_id' => $student->id,
                    'phone' => $student->phone,
                ]);

                continue;
            }

            WhatsAppMessageHistory::create([
                'session_id' => $session->id,
                'sender_user_id' => $session->user_id,
                'recipient_phone' => $phoneNumber,
                'message_type' => 'text',
                'message_content' => $processedMessage,
                'status' => WhatsAppMessageStatus::QUEUED,
            ]);

            $delay = SendWhatsAppMessageJob::getStaggeredDelay((string) $session->id);

            SendWhatsAppMessageJob::dispatch(
                (string) $session->id,
                $phoneNumber,
                $processedMessage,
                'text',
                $student->id,
                ['sender_user_id' => $session->user_id],
            )->delay(now()->addSeconds($delay));

            $messagesQueued++;
        }

        return $messagesQueued;
    }

    public function runEveningPass(Group $group, string $date): array
    {
        $preview = $this->previewMatches($group, $date);
        $session = $preview['session'];

        if (! $session?->isConnected()) {
            return [
                'status' => 'skipped',
                'reason' => 'no_connected_session',
                'matched_count' => 0,
                'marked_count' => 0,
                'reminder_count' => 0,
            ];
        }

        $markedCount = $this->markAttendance($group, $date, $preview['matched_student_ids']);
        $reminderCount = $this->sendRemindersToUnmarkedStudents($group, $date);

        return [
            'status' => 'completed',
            'matched_count' => $preview['matched_count'],
            'marked_count' => $markedCount,
            'reminder_count' => $reminderCount,
            'session_id' => $session->id,
            'session_user_id' => $session->user_id,
        ];
    }

    public function runClosePass(Group $group, string $date): array
    {
        $preview = $this->previewMatches($group, $date);
        $session = $preview['session'];

        if (! $session?->isConnected()) {
            return [
                'status' => 'skipped',
                'reason' => 'no_connected_session',
                'matched_count' => 0,
                'marked_count' => 0,
                'reminder_count' => 0,
            ];
        }

        $markedCount = $this->markAttendance($group, $date, $preview['matched_student_ids']);

        return [
            'status' => 'completed',
            'matched_count' => $preview['matched_count'],
            'marked_count' => $markedCount,
            'reminder_count' => 0,
            'session_id' => $session->id,
            'session_user_id' => $session->user_id,
        ];
    }

    public function resolveSession(Group $group): ?WhatsAppSession
    {
        return $this->sessionResolver->resolveForGroup($group);
    }

    protected function fetchWhatsAppSenders(Group $group, string $date): array
    {
        if (! $group->whatsapp_group_jid) {
            return [];
        }

        $session = $this->sessionResolver->resolveForGroup($group);

        if (! $session?->isConnected()) {
            return [];
        }

        try {
            $submissionType = $group->message_submission_type ?? MessageSubmissionType::Media;
            $phones = $this->whatsAppService->getGroupAttendeesForDate(
                $session->name,
                $group->whatsapp_group_jid,
                $date,
                $submissionType->whatsappMessageTypes(),
            );

            return $this->filterIgnoredPhones($phones, $group);
        } catch (\Throwable $exception) {
            Log::error('Failed to fetch WhatsApp group attendees', [
                'group_id' => $group->id,
                'date' => $date,
                'error' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    protected function filterIgnoredPhones(array $phones, Group $group): array
    {
        $ignoredSuffixes = collect($group->ignored_names_phones ?? [])
            ->map(fn (mixed $value) => PhoneHelper::suffix((string) $value))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($ignoredSuffixes === []) {
            return $phones;
        }

        return array_values(array_filter($phones, function (string $phone) use ($ignoredSuffixes): bool {
            $suffix = PhoneHelper::suffix($phone);

            return $suffix === null || ! in_array($suffix, $ignoredSuffixes, true);
        }));
    }

    protected function getUnmarkedStudents(Group $group, string $date): EloquentCollection
    {
        return $group->students()
            ->whereDoesntHave('progresses', fn ($query) => $query->where('date', $date))
            ->orderBy('order_no')
            ->get();
    }

    protected function resolveReminderTemplate(Group $group): string
    {
        return $group->getDefaultMessageTemplate()?->content ?: self::DEFAULT_REMINDER_TEMPLATE;
    }
}