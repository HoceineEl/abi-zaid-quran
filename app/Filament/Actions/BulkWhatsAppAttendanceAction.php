<?php

namespace App\Filament\Actions;

use App\Models\Group;
use App\Models\WhatsAppSession;
use App\Services\WhatsAppAttendanceService;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Collection;

class BulkWhatsAppAttendanceAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'bulk_whatsapp_attendance';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('حضور تلقائي جماعي')
            ->icon('heroicon-o-signal')
            ->color('success')
            ->modalWidth(MaxWidth::SevenExtraLarge)
            ->modalContent(fn ($livewire) => view('filament.actions.bulk-whatsapp-attendance-modal', [
                'preview' => $this->derivePreviewFromData($this->getMountedActionData($livewire)),
                'date' => today()->format('Y-m-d'),
            ]))
            ->modalSubmitActionLabel('تأكيد الحضور')
            ->visible(fn (): bool => WhatsAppSession::getUserSession(auth()->id())?->isConnected() === true)
            ->form([
                Hidden::make('preview_payload')
                    ->default(fn (): string => json_encode($this->buildPreview(), JSON_UNESCAPED_UNICODE)),
                Toggle::make('mark_others_absent')
                    ->label('تسجيل البقية كغائبين')
                    ->default(false)
                    ->reactive(),
                Toggle::make('remind_remaining_students')
                    ->label('تذكير البقية')
                    ->default(false)
                    ->reactive()
                    ->helperText('سيتم إرسال تذكير اليوم فقط للطلاب الذين بقوا بدون تسجيل بعد تطبيق الحضور.'),
            ])
            ->action(function (array $data): void {
                $preview = $this->parsePreviewPayload($data['preview_payload'] ?? null);

                if (($preview['totals']['ready_group_count'] ?? 0) === 0) {
                    Notification::make()
                        ->warning()
                        ->title('لا توجد مجموعات جاهزة')
                        ->body('لا توجد مجموعات مرتبطة بواتساب وجاهزة للمعالجة حالياً.')
                        ->send();

                    return;
                }

                try {
                    $result = app(WhatsAppAttendanceService::class)->applyBulkPreview(
                        $preview,
                        (bool) ($data['mark_others_absent'] ?? false),
                        (bool) ($data['remind_remaining_students'] ?? false),
                    );
                } catch (\Throwable $exception) {
                    Notification::make()
                        ->danger()
                        ->title('فشل تنفيذ الحضور الجماعي')
                        ->body($exception->getMessage())
                        ->send();

                    return;
                }

                $body = "تمت معالجة {$result['groups_processed']} مجموعة";
                $body .= "، وتسجيل حضور {$result['students_marked_present']} طالب";

                if ($result['students_marked_absent'] > 0) {
                    $body .= "، وتسجيل {$result['students_marked_absent']} غائبين";
                }

                if ($result['reminders_queued'] > 0) {
                    $body .= "، وجدولة {$result['reminders_queued']} تذكير";
                }

                if ($result['invalid_reminder_phones'] > 0) {
                    $body .= "، مع {$result['invalid_reminder_phones']} أرقام غير صالحة";
                }

                if ($result['reminder_failures'] > 0 || ! empty($result['errors'])) {
                    $body .= '، مع بعض الإخفاقات الجزئية';
                }

                Notification::make()
                    ->success()
                    ->title('تم تنفيذ الحضور الجماعي')
                    ->body($body)
                    ->send();
            });
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildPreview(): array
    {
        return app(WhatsAppAttendanceService::class)->buildBulkPreview(
            $this->getTargetGroups(),
            today()->format('Y-m-d'),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function derivePreviewFromData(array $data): array
    {
        $preview = $this->parsePreviewPayload($data['preview_payload'] ?? null);
        $readyGroups = collect($preview['groups'] ?? [])->where('status', 'ready')->values();
        $markOthersAbsent = (bool) ($data['mark_others_absent'] ?? false);
        $remindRemainingStudents = (bool) ($data['remind_remaining_students'] ?? false);

        $preview['totals'] = [
            'group_count' => count($preview['groups'] ?? []),
            'ready_group_count' => $readyGroups->count(),
            'skipped_group_count' => collect($preview['groups'] ?? [])->where('status', '!=', 'ready')->count(),
            'total_students' => $readyGroups->sum('total_students'),
            'matched_students' => $readyGroups->sum(fn (array $group) => count($group['matched_student_ids'] ?? [])),
            'already_present_students' => $readyGroups->sum(fn (array $group) => count($group['already_present_ids'] ?? [])),
            'to_mark_present_students' => $readyGroups->sum(fn (array $group) => count($group['to_mark_present_ids'] ?? [])),
            'remaining_students' => $markOthersAbsent
                ? 0
                : $readyGroups->sum(fn (array $group) => count($group['remaining_student_ids'] ?? [])),
            'planned_absent_students' => $markOthersAbsent
                ? $readyGroups->sum(fn (array $group) => count($group['remaining_student_ids'] ?? []))
                : 0,
            'planned_reminders' => ($remindRemainingStudents && ! $markOthersAbsent)
                ? $readyGroups->sum('remaining_with_valid_phone_count')
                : 0,
            'planned_invalid_reminder_phones' => ($remindRemainingStudents && ! $markOthersAbsent)
                ? $readyGroups->sum('remaining_invalid_phone_count')
                : 0,
        ];

        return $preview;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getMountedActionData(object $livewire): array
    {
        $mountedActionsData = data_get($livewire, 'mountedActionsData', []);

        if (! is_array($mountedActionsData) || $mountedActionsData === []) {
            return [];
        }

        $lastData = end($mountedActionsData);

        return is_array($lastData) ? $lastData : [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function parsePreviewPayload(?string $payload): array
    {
        $decoded = json_decode($payload ?? '', true);

        if (! is_array($decoded)) {
            return $this->buildPreview();
        }

        return $decoded;
    }

    /**
     * @return Collection<int, Group>
     */
    protected function getTargetGroups(): Collection
    {
        $livewire = $this->getLivewire();

        if (method_exists($livewire, 'getBulkWhatsAppAttendanceGroups')) {
            /** @var Collection<int, Group> $groups */
            $groups = $livewire->getBulkWhatsAppAttendanceGroups();

            return $groups;
        }

        return Group::query()
            ->with([
                'students.progresses' => fn ($query) => $query
                    ->where('date', today()->format('Y-m-d'))
                    ->select(['id', 'student_id', 'date', 'status', 'with_reason', 'comment']),
                'messageTemplates',
            ])
            ->get();
    }
}
