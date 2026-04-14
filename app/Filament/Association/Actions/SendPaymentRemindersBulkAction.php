<?php

namespace App\Filament\Association\Actions;

use Filament\Actions\BulkAction;
use App\Models\Memorizer;
use App\Models\WhatsAppSession;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;

class SendPaymentRemindersBulkAction extends BulkAction
{
    public static function getDefaultName(): ?string
    {
        return 'bulk_send_payment_reminders';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('تذكير بالدفع')
            ->icon('heroicon-o-bell-alert')
            ->color('warning')
            ->visible(fn (): bool => auth()->user()?->hasAssociationAccess() ?? false)
            ->modalHeading('إرسال تذكيرات الدفع للطلاب المحددين')
            ->modalSubmitActionLabel('إرسال التذكيرات')
            ->modalWidth('2xl')
            ->form(fn (): array => SendPaymentRemindersAction::buildForm())
            ->deselectRecordsAfterCompletion()
            ->action(fn (Collection $records, array $data) => $this->dispatchForSelected($records, $data));
    }

    protected function dispatchForSelected(Collection $records, array $data): void
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

        $session->update(['payment_reminder_template' => $template]);

        // Filter selected records: only non-exempt, unpaid for the chosen month
        $memorizers = $records
            ->load(['group:id,name', 'guardian:id,phone'])
            ->reject(fn (Memorizer $m) => $m->exempt)
            ->reject(fn (Memorizer $m) => $m->payments()
                ->whereYear('payment_date', $year)
                ->whereMonth('payment_date', $month)
                ->exists()
            );

        if ($memorizers->isEmpty()) {
            Notification::make()
                ->title('لا يوجد طلاب مستهدفون')
                ->body('جميع الطلاب المحددين قد سدّدوا اشتراكاتهم أو هم معفيون.')
                ->warning()
                ->send();

            return;
        }

        $monthLabel = Carbon::createFromDate($year, $month, 1)
            ->locale('ar')
            ->translatedFormat('F Y');

        [$queued, $skipped] = SendPaymentRemindersAction::queueRemindersFor(
            $memorizers,
            $session,
            $template,
            $monthLabel,
            $year,
            $month,
        );

        $body = "تم جدولة {$queued} تذكير للإرسال عبر واتساب من أصل {$records->count()} طالب محدد.";

        if (! empty($skipped)) {
            $body .= "\n\nلم يُرسَل إليهم (لا يوجد رقم هاتف): " . implode('، ', $skipped);
        }

        Notification::make()
            ->title('تم جدولة تذكيرات الدفع!')
            ->body($body)
            ->success()
            ->send();
    }
}
