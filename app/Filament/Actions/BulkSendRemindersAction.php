<?php

namespace App\Filament\Actions;

use App\Helpers\PhoneHelper;
use App\Models\Group;
use App\Models\WhatsAppSession;
use App\Services\WhatsAppAttendanceService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Wizard\Step;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables\Actions\BulkAction;
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
            ->modalWidth(MaxWidth::Large)
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
                        ])->whereKey($groupIds)->get()->map(fn (Group $group) => [
                            'name'          => $group->name,
                            'with_phone'    => $group->students
                                ->filter(fn ($s) => $s->progresses->isEmpty())
                                ->filter(fn ($s) => filled(PhoneHelper::cleanPhoneNumber($s->phone)))
                                ->count(),
                            'without_phone' => $group->students
                                ->filter(fn ($s) => $s->progresses->isEmpty())
                                ->filter(fn ($s) => blank(PhoneHelper::cleanPhoneNumber($s->phone)))
                                ->count(),
                        ])->filter(fn ($g) => ($g['with_phone'] + $g['without_phone']) > 0)->values()->all();

                        $set('groups_summary', json_encode($summary, JSON_UNESCAPED_UNICODE));
                    }),

                Step::make('confirm')
                    ->label('التأكيد')
                    ->icon('heroicon-o-check-circle')
                    ->schema([
                        Placeholder::make('summary_display')
                            ->label('')
                            ->content(function (Get $get): HtmlString {
                                $groups      = json_decode($get('groups_summary') ?? '[]', true) ?? [];
                                $totalSend   = array_sum(array_column($groups, 'with_phone'));
                                $totalSkip   = array_sum(array_column($groups, 'without_phone'));
                                $date        = $get('date') ?? today()->format('Y-m-d');

                                if (empty($groups)) {
                                    return new HtmlString('<p class="text-sm text-gray-500">لا يوجد طلاب غائبون غير مسجلين في المجموعات المحددة.</p>');
                                }

                                $rows = collect($groups)->map(fn ($g) => "
                                    <div class='rounded-xl bg-gray-50 px-3 py-2 dark:bg-gray-800/60'>
                                        <div class='flex items-center justify-between'>
                                            <span class='text-sm text-gray-700 dark:text-gray-200'>{$g['name']}</span>
                                            <div class='flex gap-1.5'>
                                                <span class='rounded-full bg-warning-50 px-2 py-0.5 text-xs font-bold text-warning-700 dark:bg-warning-500/10 dark:text-warning-400'>{$g['with_phone']} تذكير</span>"
                                                . ($g['without_phone'] > 0 ? "<span class='rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-500 dark:bg-white/10'>{$g['without_phone']} بدون رقم</span>" : '')
                                                . "
                                            </div>
                                        </div>
                                    </div>")->implode('');

                                $skipNote = $totalSkip > 0
                                    ? "<p class='mt-2 text-xs text-gray-400'>سيتم تخطي {$totalSkip} طالب بدون رقم واتساب صالح.</p>"
                                    : '';

                                return new HtmlString("
                                    <div class='space-y-2' dir='rtl'>
                                        <p class='text-sm font-medium text-gray-700 dark:text-gray-200'>
                                            سيتم إرسال <strong class='text-warning-600'>{$totalSend}</strong> تذكير في تاريخ {$date}:
                                        </p>
                                        <div class='space-y-1.5 mt-2'>{$rows}</div>
                                        {$skipNote}
                                    </div>");
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
                } catch (\Throwable $exception) {
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
