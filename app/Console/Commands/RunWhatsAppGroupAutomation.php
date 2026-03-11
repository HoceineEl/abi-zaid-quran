<?php

namespace App\Console\Commands;

use App\Jobs\RunGroupWhatsAppAutomationJob;
use App\Models\Group;
use App\Models\GroupAutomationRun;
use App\Services\GroupWhatsAppAutomationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RunWhatsAppGroupAutomation extends Command
{
    protected $signature = 'groups:run-whatsapp-automation {--date=} {--time=}';

    protected $description = 'Dispatch scheduled WhatsApp attendance and reminder automation for groups';

    public function handle(): int
    {
        $now = $this->resolveCurrentMoment();
        $runDate = $now->toDateString();
        $dispatched = 0;

        $groups = Group::withoutGlobalScopes()
            ->whereNotNull('whatsapp_group_jid')
            ->get();

        foreach ($groups as $group) {
            if ($this->isDueForEveningPass($group->id, $runDate, $now)) {
                RunGroupWhatsAppAutomationJob::dispatch(
                    $group->id,
                    $runDate,
                    GroupWhatsAppAutomationService::EVENING_REMINDER_PASS,
                );
                $dispatched++;
            }

            if ($this->isDueForClosePass($group->id, $runDate, $now, (string) $group->auto_attendance_close_time)) {
                RunGroupWhatsAppAutomationJob::dispatch(
                    $group->id,
                    $runDate,
                    GroupWhatsAppAutomationService::CLOSE_PASS,
                );
                $dispatched++;
            }
        }

        $this->info("Dispatched {$dispatched} WhatsApp automation job(s) for {$runDate}.");

        return self::SUCCESS;
    }

    protected function resolveCurrentMoment(): Carbon
    {
        $base = $this->option('date')
            ? Carbon::parse((string) $this->option('date'))
            : now();

        if (! $this->option('time')) {
            return $base;
        }

        [$hours, $minutes] = explode(':', (string) $this->option('time')) + [0, 0];

        return $base->copy()->setTime((int) $hours, (int) $minutes);
    }

    protected function isDueForEveningPass(int $groupId, string $runDate, Carbon $now): bool
    {
        $eveningTime = Carbon::parse($runDate.' '.config('whatsapp.automation.evening_time', '20:00'));

        return $now->greaterThanOrEqualTo($eveningTime)
            && ! $this->hasBlockingRun($groupId, $runDate, GroupWhatsAppAutomationService::EVENING_REMINDER_PASS);
    }

    protected function isDueForClosePass(int $groupId, string $runDate, Carbon $now, string $closeTime): bool
    {
        $closeMoment = Carbon::parse($runDate.' '.substr($closeTime, 0, 5));

        return $now->greaterThanOrEqualTo($closeMoment)
            && ! $this->hasBlockingRun($groupId, $runDate, GroupWhatsAppAutomationService::CLOSE_PASS);
    }

    protected function hasBlockingRun(int $groupId, string $runDate, string $phase): bool
    {
        return GroupAutomationRun::query()
            ->where('group_id', $groupId)
            ->whereDate('run_date', $runDate)
            ->where('phase', $phase)
            ->whereIn('status', ['running', 'completed'])
            ->exists();
    }
}
