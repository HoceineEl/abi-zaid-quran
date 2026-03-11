<?php

namespace App\Filament\Actions;

use App\Enums\MessageSubmissionType;
use App\Helpers\PhoneHelper;
use App\Models\Group;
use App\Services\GroupWhatsAppSessionResolver;
use App\Services\WhatsAppService;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;
use Illuminate\Support\Facades\Log;

class AutoAttendanceAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'auto_attendance';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('حضور تلقائي')
            ->icon('heroicon-o-signal')
            ->color('success')
            ->fillForm(function () {
                $date = today()->format('Y-m-d');
                $result = $this->fetchAndMatchAttendees($date);

                return ['student_ids' => $result['matched_student_ids']];
            })
            ->form($this->getFormSchema())
            ->modalSubmitActionLabel('تأكيد الحضور')
            ->action(fn (array $data) => $this->processAttendance($data));
    }

    protected function getFormSchema(): array
    {
        $date = today()->format('Y-m-d');

        return [
            Placeholder::make('match_stats')
                ->label('إحصائيات المطابقة')
                ->content(function () use ($date) {
                    $result = $this->fetchAndMatchAttendees($date);

                    return "تم مطابقة {$result['matched_count']} من {$result['total_students']} طالب";
                }),
            CheckboxList::make('student_ids')
                ->label('الطلاب')
                ->options(fn () => $this->getOwnerGroup()
                    ->students()
                    ->orderBy('order_no')
                    ->pluck('name', 'id'))
                ->descriptions(fn () => $this->fetchAndMatchAttendees($date)['descriptions'])
                ->disableOptionWhen(fn (string $value) => in_array(
                    (int) $value,
                    $this->fetchAndMatchAttendees($date)['already_present_ids'],
                ))
                ->bulkToggleable()
                ->columns(2)
                ->required(),
        ];
    }

    protected function fetchAndMatchAttendees(string $date): array
    {
        static $cache = [];

        if (isset($cache[$date])) {
            return $cache[$date];
        }

        $group = $this->getOwnerGroup();
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
                $descriptions[$student->id] = "$phone — مسجل مسبقاً";

                continue;
            }

            if (PhoneHelper::matchesAny($phone, $senderIndex)) {
                $matchedStudentIds[] = $student->id;
                $descriptions[$student->id] = "$phone — تم المطابقة من واتساب";
            } else {
                $descriptions[$student->id] = $phone;
            }
        }

        return $cache[$date] = [
            'matched_student_ids' => $matchedStudentIds,
            'already_present_ids' => $alreadyPresentIds,
            'descriptions' => $descriptions,
            'matched_count' => count($matchedStudentIds),
            'total_students' => $students->count(),
        ];
    }

    protected function fetchWhatsAppSenders(Group $group, string $date): array
    {
        if (! $group->whatsapp_group_jid) {
            return [];
        }

        try {
            $session = app(GroupWhatsAppSessionResolver::class)->resolveForGroup($group);

            if (! $session?->isConnected()) {
                return [];
            }

            $submissionType = $group->message_submission_type ?? MessageSubmissionType::Media;

            return app(WhatsAppService::class)->getGroupAttendeesForDate(
                $session->name,
                $group->whatsapp_group_jid,
                $date,
                $submissionType->whatsappMessageTypes()
            );
        } catch (\Exception $e) {
            Log::error('Failed to fetch WhatsApp group attendees', [
                'group_id' => $group->id,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    protected function processAttendance(array $data): void
    {
        $group = $this->getOwnerGroup();
        $date = today()->format('Y-m-d');
        $studentIds = $data['student_ids'] ?? [];

        if (empty($studentIds)) {
            Notification::make()
                ->warning()
                ->title('لم يتم اختيار أي طالب')
                ->send();

            return;
        }

        $existingProgress = $group->progresses()
            ->where('date', $date)
            ->whereIn('student_id', $studentIds)
            ->get()
            ->keyBy('student_id');

        $createdCount = 0;
        $attendanceData = ['status' => 'memorized', 'comment' => 'whatsapp_auto_attendance'];

        foreach ($studentIds as $studentId) {
            $progress = $existingProgress->get($studentId);

            if ($progress?->status === 'memorized') {
                continue;
            }

            if ($progress) {
                $progress->update($attendanceData);
            } else {
                $group->students()->find($studentId)?->progresses()->create(
                    ['date' => $date, ...$attendanceData],
                );
            }

            $createdCount++;
        }

        Notification::make()
            ->success()
            ->title("تم تسجيل حضور {$createdCount} طالب بنجاح")
            ->send();
    }

    protected function getOwnerGroup(): Group
    {
        return $this->getLivewire()->ownerRecord;
    }
}
