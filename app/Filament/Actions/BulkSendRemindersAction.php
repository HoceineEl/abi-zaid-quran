<?php

namespace App\Filament\Actions;

use Filament\Actions\BulkAction;
use Filament\Support\Enums\Width;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Throwable;
use App\Helpers\PhoneHelper;
use App\Models\Group;
use App\Models\WhatsAppSession;
use App\Services\WhatsAppAttendanceService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;

class BulkSendRemindersAction extends BulkAction
{
    public static function getDefaultName(): ?string
    {
        return 'bulk_send_reminders';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('تذكير الغائبين')
            ->icon('heroicon-o-bell')
            ->color('warning')
            ->modalWidth(Width::Large)
            ->modalHeading('إرسال تذكيرات للغائبين')
            ->modalSubmitActionLabel('إرسال التذكيرات')
            ->visible(fn (): bool => WhatsAppSession::getUserSession(auth()->id())?->isConnected() === true)
            ->steps([
                Step::make('date')
                    ->label('التاريخ')
                    ->icon('heroicon-o-calendar')
                    ->schema([
                        DatePicker::make('date')
                            ->label('تاريخ الحضور')
                            ->default(today())
                            ->required()
                            ->native(false)
                            ->displayFormat('Y-m-d'),
                        Hidden::make('groups_summary'),
                    ])
                    ->afterValidation(function (Get $get, Set $set): void {
                        $date     = $get('date') ?? today()->format('Y-m-d');
                        $groupIds = $this->getLivewire()->selectedTableRecords ?? [];

                        $summary = Group::with([
                            'students.progresses' => fn ($q) => $q
                                ->whereDate('date', $date)
                                ->select(['id', 'student_id', 'date', 'status']),
                        ])->whereKey($groupIds)->get()->map(function (Group $group) {
                            $absent = $group->students->filter(fn ($s) => $s->progresses->isEmpty());
                            return [
                                'name'          => $group->name,
                                'with_phone'    => $absent->filter(fn ($s) => filled(PhoneHelper::cleanPhoneNumber($s->phone)))->count(),
                                'without_phone' => $absent->filter(fn ($s) => blank(PhoneHelper::cleanPhoneNumber($s->phone)))->count(),
                            ];
                        })->filter(fn ($g) => ($g['with_phone'] + $g['without_phone']) > 0)->values()->all();

                        $set('groups_summary', json_encode($summary, JSON_UNESCAPED_UNICODE));
                    }),

                Step::make('confirm')
                    ->label('التأكيد')
                    ->icon('heroicon-o-check-circle')
                    ->schema([
                        Placeholder::make('summary_display')
                            ->label('')
                            ->content(function (Get $get): HtmlString {
                                $groups    = json_decode($get('groups_summary') ?? '[]', true) ?? [];
                                $totalSend = array_sum(array_column($groups, 'with_phone'));
                                $totalSkip = array_sum(array_column($groups, 'without_phone'));
                                $date      = $get('date') ?? today()->format('Y-m-d');

                                if (empty($groups)) {
                                    return new HtmlString('<p class="text-sm text-gray-500">لا يوجد طلاب غائبون غير مسجلين في المجموعات المحددة.</p>');
                                }

                                return new HtmlString(
                                    view('filament.actions.bulk-send-reminders-modal', compact('groups', 'totalSend', 'totalSkip', 'date'))->render()
                                );
                            }),
                    ]),
            ])
            ->action(function (array $data): void {
                $date     = $data['date'];
                $groupIds = $this->getLivewire()->selectedTableRecords ?? [];

                $records = Group::with([
                    'students.progresses' => fn ($q) => $q
                        ->whereDate('date', $date)
                        ->select(['id', 'student_id', 'date', 'status', 'with_reason', 'comment']),
                    'messageTemplates',
                ])->whereKey($groupIds)->get();

                // Build a minimal preview with empty to_mark_present_ids so
                // applyBulkPreview only sends reminders without marking anyone present.
                $preview = [
                    'date'   => $date,
                    'groups' => $records->map(fn (Group $group) => [
                        'group_id'            => $group->id,
                        'group_name'          => $group->name,
                        'status'              => 'ready',
                        'skip_reason'         => null,
                        'to_mark_present_ids' => [],
                    ])->all(),
                ];

                try {
                    $result = app(WhatsAppAttendanceService::class)->applyBulkPreview(
                        $preview,
                        markOthersAbsent: false,
                        remindRemainingStudents: true,
                    );
                } catch (Throwable $exception) {
                    Notification::make()
                        ->danger()
                        ->title('فشل إرسال التذكيرات')
                        ->body($exception->getMessage())
                        ->send();

                    return;
                }

                $body = "تم جدولة {$result['reminders_queued']} تذكير";

                if ($result['invalid_reminder_phones'] > 0) {
                    $body .= "، وتخطي {$result['invalid_reminder_phones']} بدون رقم صالح";
                }

                Notification::make()
                    ->success()
                    ->title('تم إرسال التذكيرات')
                    ->body($body)
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }
}
