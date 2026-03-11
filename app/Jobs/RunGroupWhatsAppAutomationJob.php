<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Group;
use App\Models\GroupAutomationRun;
use App\Services\GroupWhatsAppAutomationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunGroupWhatsAppAutomationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public int $groupId,
        public string $date,
        public string $phase,
    ) {}

    public function handle(GroupWhatsAppAutomationService $automationService): void
    {
        $run = GroupAutomationRun::firstOrCreate(
            [
                'group_id' => $this->groupId,
                'run_date' => $this->date,
                'phase' => $this->phase,
            ],
            [
                'status' => 'running',
                'started_at' => now(),
            ],
        );

        if (! $run->wasRecentlyCreated) {
            if (in_array($run->status, ['running', 'completed'], true)) {
                return;
            }

            $wasClaimed = GroupAutomationRun::query()
                ->whereKey($run->id)
                ->where('status', $run->status)
                ->update([
                    'status' => 'running',
                    'started_at' => now(),
                    'completed_at' => null,
                    'error_message' => null,
                ]);

            if ($wasClaimed === 0) {
                return;
            }

            $run->refresh();
        }

        $group = Group::withoutGlobalScopes()->find($this->groupId);

        if (! $group || ! $group->whatsapp_group_jid) {
            $run->update([
                'status' => 'skipped',
                'details' => ['reason' => 'group_not_eligible'],
                'completed_at' => now(),
            ]);

            return;
        }

        try {
            $result = match ($this->phase) {
                GroupWhatsAppAutomationService::EVENING_REMINDER_PASS => $automationService->runEveningPass($group, $this->date),
                GroupWhatsAppAutomationService::CLOSE_PASS => $automationService->runClosePass($group, $this->date),
                default => ['status' => 'skipped', 'reason' => 'unknown_phase'],
            };

            $run->update([
                'status' => $result['status'] ?? 'completed',
                'details' => $result,
                'completed_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            $run->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'completed_at' => now(),
            ]);

            Log::error('Group WhatsApp automation failed', [
                'group_id' => $this->groupId,
                'date' => $this->date,
                'phase' => $this->phase,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
