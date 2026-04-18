<?php

namespace App\Services;

use App\Enums\Troubles;
use App\Models\Memorizer;
use App\Models\User;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;

class AttendanceActionService
{
    /**
     * Update today's attendance for a memorizer with evaluation notes.
     * Returns true on success, false if no attendance record exists for today.
     */
    public static function saveNotes(Memorizer $memorizer, array $data): bool
    {
        $attendance = $memorizer->attendances()
            ->whereDate('date', now()->toDateString())
            ->first();

        if (! $attendance) {
            return false;
        }

        $attendance->update([
            'notes' => $data['behavioral_issues'] ?? [],
            'score' => $data['score'],
            'custom_note' => $data['custom_note'] ?? null,
        ]);

        return true;
    }

    /**
     * Notify association admins about a single memorizer's behavioral issues.
     */
    public static function notifyAdminsOfTroubles(Memorizer $memorizer, array $troubles, string $groupName, ?NotificationAction $viewAction = null): void
    {
        if (empty($troubles)) {
            return;
        }

        $admins = self::associationAdmins();
        if ($admins->isEmpty()) {
            return;
        }

        $labels = self::formatTroubleLabels($troubles);

        $notification = Notification::make()
            ->title("مشكلة سلوكية للطالب {$memorizer->name}")
            ->body("قام الطالب {$memorizer->name} في مجموعة {$groupName} بـ {$labels} بتاريخ " . now()->format('Y-m-d'))
            ->warning();

        if ($viewAction) {
            $notification->actions([$viewAction]);
        }

        $notification->sendToDatabase($admins);
    }

    /**
     * Notify admins about a batch of memorizers with troubles (aggregated into one notification).
     *
     * @param  array<array{memorizer: Memorizer, troubles: array}>  $entries
     */
    public static function notifyAdminsOfBulkTroubles(array $entries, string $groupName, ?NotificationAction $viewAction = null): void
    {
        $entries = array_values(array_filter($entries, fn (array $entry): bool => ! empty($entry['troubles'])));

        if (empty($entries)) {
            return;
        }

        $admins = self::associationAdmins();
        if ($admins->isEmpty()) {
            return;
        }

        $lines = array_map(function (array $entry): string {
            $labels = self::formatTroubleLabels($entry['troubles']);

            return "• {$entry['memorizer']->name}: {$labels}";
        }, $entries);

        $count = count($entries);

        $notification = Notification::make()
            ->title("ملاحظات سلوكية لـ {$count} طلاب — مجموعة {$groupName}")
            ->body(implode("\n", $lines) . "\n\nبتاريخ " . now()->format('Y-m-d'))
            ->warning();

        if ($viewAction) {
            $notification->actions([$viewAction]);
        }

        $notification->sendToDatabase($admins);
    }

    /**
     * Build a WhatsApp dispatch payload for a memorizer, or null if no phone is available.
     *
     * @return array{url: string, phone: string, is_parent: bool, message: string}|null
     */
    public static function buildWhatsAppDispatch(Memorizer $memorizer, string $messageType, ?string $overrideMessage = null): ?array
    {
        $rawPhone = self::resolvePhone($memorizer);
        if (! $rawPhone) {
            return null;
        }

        $phone = preg_replace('/[^0-9]/', '', $rawPhone);
        $message = $overrideMessage ?? $memorizer->getMessageToSend($messageType);
        $isParent = ! $memorizer->phone && (bool) $memorizer->guardian?->phone;

        return [
            'url' => "https://wa.me/{$phone}?text=" . rawurlencode($message),
            'phone' => $phone,
            'is_parent' => $isParent,
            'message' => $message,
        ];
    }

    /**
     * Persist a ReminderLog entry for a WhatsApp dispatch.
     *
     * @param  array{phone: string, is_parent: bool, message: string}  $dispatch
     */
    public static function logWhatsAppReminder(Memorizer $memorizer, string $messageType, array $dispatch): void
    {
        $memorizer->reminderLogs()->create([
            'type' => $messageType,
            'phone_number' => $dispatch['phone'],
            'message' => mb_substr($dispatch['message'], 0, 50),
            'is_parent' => $dispatch['is_parent'],
        ]);
    }

    public static function resolvePhone(Memorizer $memorizer): ?string
    {
        return $memorizer->phone ?: $memorizer->guardian?->phone;
    }

    private static function associationAdmins(): Collection
    {
        return User::where('email', 'LIKE', '%@association.com')->get();
    }

    private static function formatTroubleLabels(array $troubles): string
    {
        return collect($troubles)
            ->map(fn (string $trouble): ?string => Troubles::tryFrom($trouble)?->getLabel())
            ->filter()
            ->implode('، ');
    }
}
