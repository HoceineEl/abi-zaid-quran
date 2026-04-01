<?php

namespace App\Filament\Actions;

use App\Models\WhatsAppSession;
use App\Services\WhatsAppAttendanceService;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables\Actions\BulkAction;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class BulkWhatsAppAttendanceAction extends BulkAction
{
    public static function getDefaultName(): ?string
    {
        return 'bulk_whatsapp_attendance';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('حضور تلقائي عبر واتساب')
            ->icon('heroicon-o-signal')
            ->color('success')
            ->modalWidth(MaxWidth::SevenExtraLarge)
            ->modalHeading('الحضور التلقائي عبر واتساب')
            ->modalSubmitActionLabel('تأكيد الحضور')
            ->visible(fn (): bool => WhatsAppSession::getUserSession(auth()->id())?->isConnected() === true)
            ->mountUsing(function (Form $form, EloquentCollection $records): void {
                $preview = app(WhatsAppAttendanceService::class)->buildBulkPreview(
                    $records->load([
                        'students.progresses' => fn ($q) => $q
                            ->where('date', today()->format('Y-m-d'))
                            ->select(['id', 'student_id', 'date', 'status', 'with_reason', 'comment']),
                        'messageTemplates',
                    ]),
                    today()->format('Y-m-d'),
                );

                $form->fill([
                    'preview_payload' => json_encode($preview, JSON_UNESCAPED_UNICODE),
                ]);
            })
            ->form([
                Hidden::make('preview_payload'),
                Toggle::make('mark_others_absent')
                    ->label('تسجيل البقية كغائبين')
                    ->default(false)
                    ->reactive(),
                Toggle::make('remind_remaining_students')
                    ->label('تذكير البقية عبر واتساب')
                    ->default(false)
                    ->reactive()
                    ->helperText('سيتم إرسال تذكير للطلاب الذين لم يسجلوا بعد.'),
            ])
            ->modalContent(fn ($livewire) => view('filament.actions.bulk-whatsapp-attendance-modal', [
                'preview' => $this->derivePreviewFromData($this->getMountedActionData($livewire)),
                'date' => today()->format('Y-m-d'),
            ]))
            ->action(function (array $data): void {
                $preview = $this->parsePreviewPayload($data['preview_payload'] ?? null);

                if (($preview['totals']['ready_group_count'] ?? 0) === 0) {
                    Notification::make()
                        ->warning()
                        ->title('لا توجد مجموعات جاهزة')
                        ->body('لا توجد مجموعات مرتبطة بواتساب وجاهزة للمعالجة.')
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

                $body = "تمت معالجة {$result['groups_processed']} مجموعة، وتسجيل حضور {$result['students_marked_present']} طالب";

                if ($result['students_marked_absent'] > 0) {
                    $body .= "، وغياب {$result['students_marked_absent']}";
                }

                if ($result['reminders_queued'] > 0) {
                    $body .= "، وجدولة {$result['reminders_queued']} تذكير";
                }

                Notification::make()
                    ->success()
                    ->title('تم تنفيذ الحضور الجماعي')
                    ->body($body)
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    protected function derivePreviewFromData(array $data): array
    {
        $preview = $this->parsePreviewPayload($data['preview_payload'] ?? null);
        $readyGroups = collect($preview['groups'] ?? [])->where('status', 'ready')->values();
        $markOthersAbsent = (bool) ($data['mark_others_absent'] ?? false);
        $remindRemainingStudents = (bool) ($data['remind_remaining_students'] ?? false);

        $preview['totals'] = [
            'group_count'                   => count($preview['groups'] ?? []),
            'ready_group_count'             => $readyGroups->count(),
            'skipped_group_count'           => collect($preview['groups'] ?? [])->where('status', '!=', 'ready')->count(),
            'total_students'                => $readyGroups->sum('total_students'),
            'matched_students'              => $readyGroups->sum(fn (array $g) => count($g['matched_student_ids'] ?? [])),
            'already_present_students'      => $readyGroups->sum(fn (array $g) => count($g['already_present_ids'] ?? [])),
            'to_mark_present_students'      => $readyGroups->sum(fn (array $g) => count($g['to_mark_present_ids'] ?? [])),
            'remaining_students'            => $markOthersAbsent ? 0 : $readyGroups->sum(fn (array $g) => count($g['remaining_student_ids'] ?? [])),
            'planned_absent_students'       => $markOthersAbsent ? $readyGroups->sum(fn (array $g) => count($g['remaining_student_ids'] ?? [])) : 0,
            'planned_reminders'             => ($remindRemainingStudents && ! $markOthersAbsent) ? $readyGroups->sum('remaining_with_valid_phone_count') : 0,
            'planned_invalid_reminder_phones' => ($remindRemainingStudents && ! $markOthersAbsent) ? $readyGroups->sum('remaining_invalid_phone_count') : 0,
        ];

        return $preview;
    }

    protected function getMountedActionData(object $livewire): array
    {
        $mountedActionsData = data_get($livewire, 'mountedActionsData', []);

        if (! is_array($mountedActionsData) || $mountedActionsData === []) {
            return [];
        }

        $lastData = end($mountedActionsData);

        return is_array($lastData) ? $lastData : [];
    }

    protected function parsePreviewPayload(?string $payload): array
    {
        $decoded = json_decode($payload ?? '', true);

        return is_array($decoded) ? $decoded : [];
    }
}
