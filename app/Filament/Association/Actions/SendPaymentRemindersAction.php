<?php

namespace App\Filament\Association\Actions;

use Filament\Actions\Action;
use App\Enums\WhatsAppMessageStatus;
use App\Helpers\PhoneHelper;
use App\Jobs\SendWhatsAppMessageJob;
use App\Models\Memorizer;
use App\Models\ReminderLog;
use App\Models\WhatsAppMessageHistory;
use App\Models\WhatsAppSession;
use Carbon\Carbon;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;

class SendPaymentRemindersAction extends Action
{
    public const DEFAULT_TEMPLATE = <<<'MSG'
السلام عليكم ورحمة الله وبركاته 🌙

وليّ أمر الطالب/الطالبة *{student_name}* الكريم/الكريمة،

تُذكِّركم إدارة جمعية ابن أبي زيد القيرواني (دار القرآن الكريم) بأن اشتراك شهر *{month}* للطالب/الطالبة *{student_name}* من مجموعة {group_name} لم يُسدَّد بعد.

نأمل منكم المبادرة إلى تسديده في أقرب وقت ممكن، وجزاكم الله خيرًا على حرصكم ومتابعتكم.

مع خالص التقدير والاحترام،
🕌 إدارة جمعية ابن أبي زيد القيرواني
MSG;

    public static function getDefaultName(): ?string
    {
        return 'bulk_payment_reminders';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('تذكير بالدفع')
            ->icon('heroicon-o-bell-alert')
            ->color('warning')
            ->visible(fn (): bool => auth()->user()?->hasAssociationAccess() ?? false)
            ->modalHeading('إرسال تذكيرات الدفع الشهري')
            ->modalDescription(fn () => self::buildModalDescription())
            ->modalSubmitActionLabel('إرسال التذكيرات')
            ->modalWidth('2xl')
            ->form(fn (): array => self::buildForm())
            ->action(fn (array $data) => self::dispatchReminders($data));
    }

    protected static function buildModalDescription(): string
    {
        $count = Memorizer::notExempt()
            ->unpaidForMonth(now()->year, now()->month)
            ->count();

        return "سيصل التذكير إلى {$count} طالب لم يسدّد اشتراكه. يمكنك تعديل الرسالة قبل الإرسال.";
    }

    public static function buildForm(): array
    {
        $session = WhatsAppSession::getUserSession(auth()->id());
        $savedTemplate = $session?->payment_reminder_template ?? self::DEFAULT_TEMPLATE;

        return [
            Select::make('year')
                ->label('السنة')
                ->options([now()->year => now()->year, now()->year - 1 => now()->year - 1])
                ->default(now()->year)
                ->required()
                ->reactive(),

            Select::make('month')
                ->label('الشهر')
                ->options(
                    collect(range(1, 12))
                        ->mapWithKeys(fn (int $m): array => [
                            $m => Carbon::createFromDate(now()->year, $m, 1)->locale('ar')->translatedFormat('F'),
                        ])
                        ->all(),
                )
                ->default(now()->month)
                ->required(),

            Textarea::make('message_template')
                ->label('نص الرسالة')
                ->rows(10)
                ->required()
                ->hint('المتغيرات المتاحة: {student_name} و {group_name} و {month}')
                ->default($savedTemplate),
        ];
    }

    protected static function dispatchReminders(array $data): void
    {
        $session = WhatsAppSession::getUserSession(auth()->id());

        if (! $session || ! $session->isConnected()) {
            Notification::make()
                ->title('جلسة واتساب غير متصلة')
                ->body('يرجى الاتصال بواتساب أولًا من صفحة إدارة الجلسة قبل إرسال التذكيرات.')
                ->danger()
                ->send();

            return;
        }

        $year = (int) $data['year'];
        $month = (int) $data['month'];
        $template = $data['message_template'];

        // Persist the edited template so it reloads next time.
        $session->update(['payment_reminder_template' => $template]);

        $memorizers = Memorizer::notExempt()
            ->unpaidForMonth($year, $month)
            ->with(['group:id,name', 'guardian:id,phone'])
            ->get();

        if ($memorizers->isEmpty()) {
            Notification::make()
                ->title('لا يوجد طلاب مستهدفون')
                ->body('جميع الطلاب قد سدّدوا اشتراكاتهم أو هم معفيون.')
                ->warning()
                ->send();

            return;
        }

        $monthLabel = Carbon::createFromDate($year, $month, 1)
            ->locale('ar')
            ->translatedFormat('F Y');

        [$queued, $skipped] = self::queueRemindersFor($memorizers, $session, $template, $monthLabel, $year, $month);

        $body = "تم جدولة {$queued} تذكير للإرسال عبر واتساب.";

        if (! empty($skipped)) {
            $body .= "\n\nلم يُرسَل إليهم (لا يوجد رقم هاتف): ".implode('، ', $skipped);
        }

        Notification::make()
            ->title('تم جدولة تذكيرات الدفع!')
            ->body($body)
            ->success()
            ->send();
    }

    /**
     * @param  Collection<int, Memorizer>  $memorizers
     * @return array{0: int, 1: array<int, string>}
     */
    public static function queueRemindersFor(
        Collection $memorizers,
        WhatsAppSession $session,
        string $template,
        string $monthLabel,
        int $year,
        int $month,
    ): array {
        $queued = 0;
        $skipped = [];

        foreach ($memorizers as $memorizer) {
            $rawPhone = $memorizer->phone ?: $memorizer->guardian?->phone;
            $phone = PhoneHelper::cleanPhoneNumber($rawPhone);

            if (! $phone) {
                $skipped[] = $memorizer->name;

                continue;
            }

            $message = self::renderTemplate($template, $memorizer, $monthLabel);
            $isParent = ! $memorizer->phone && (bool) $memorizer->guardian?->phone;

            // Create ReminderLog synchronously so the row turns yellow immediately on refresh.
            ReminderLog::create([
                'memorizer_id' => $memorizer->id,
                'type' => 'payment',
                'phone_number' => $phone,
                'message' => $message,
                'is_parent' => $isParent,
            ]);

            WhatsAppMessageHistory::create([
                'session_id' => $session->id,
                'sender_user_id' => auth()->id(),
                'recipient_phone' => $phone,
                'recipient_name' => $memorizer->name,
                'message_type' => 'text',
                'message_content' => $message,
                'status' => WhatsAppMessageStatus::QUEUED,
                'metadata' => [
                    'memorizer_id' => $memorizer->id,
                    'memorizer_name' => $memorizer->name,
                    'payment_reminder' => true,
                    'month' => $month,
                    'year' => $year,
                ],
            ]);

            SendWhatsAppMessageJob::dispatch(
                $session->id,
                $phone,
                $message,
                'text',
                null,
                ['sender_user_id' => auth()->id(), 'payment_reminder' => true],
            )->delay(now()->addSeconds(SendWhatsAppMessageJob::getStaggeredDelay($session->id)));

            $queued++;
        }

        return [$queued, $skipped];
    }

    protected static function renderTemplate(string $template, Memorizer $memorizer, string $monthLabel): string
    {
        return str_replace(
            ['{student_name}', '{group_name}', '{month}'],
            [$memorizer->name, $memorizer->group?->name ?? '—', $monthLabel],
            $template,
        );
    }
}
