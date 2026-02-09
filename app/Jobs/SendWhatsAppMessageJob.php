<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\MessageResponseStatus;
use App\Enums\WhatsAppMessageStatus;
use App\Jobs\Middleware\WhatsAppRateLimited;
use App\Models\Student;
use App\Models\StudentDisconnection;
use App\Models\WhatsAppMessageHistory;
use App\Models\WhatsAppSession;
use App\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendWhatsAppMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $maxExceptions = 3;

    public int $backoff = 5;

    public int $timeout = 30;

    public function __construct(
        public string $sessionId,
        public string $to,
        public string $message,
        public string $type = 'text',
        public ?int $studentId = null,
        public ?array $metadata = null,
    ) {
        $this->onQueue('whatsapp');
    }

    /**
     * Calculate staggered delay for message dispatch.
     *
     * Uses an atomic counter per session to assign each message a unique position,
     * spacing them by the configured interval. The counter auto-expires after 10 minutes.
     */
    public static function getStaggeredDelay(string $sessionId): int
    {
        $counterKey = "whatsapp_batch_counter:{$sessionId}";
        $delayMin = (int) config('whatsapp.delay_min', 8);
        $delayMax = (int) config('whatsapp.delay_max', 20);

        $position = Cache::increment($counterKey);
        Cache::put($counterKey, $position, now()->addMinutes(10));

        if ($position <= 1) {
            return 0;
        }

        // Each message gets a random delay between min and max, cumulated by position
        return ($position - 1) * rand($delayMin, $delayMax);
    }

    public function middleware(): array
    {
        return [
            new WhatsAppRateLimited((int) config('whatsapp.messages_per_minute', 10)),
            (new WithoutOverlapping($this->to))->expireAfter(30),
        ];
    }

    public function handle(WhatsAppService $whatsappService): void
    {
        try {
            $this->validateSession();

            $result = $whatsappService->sendTextMessage(
                $this->sessionId,
                $this->to,
                $this->message
            );

            $this->updateMessageHistory(
                WhatsAppMessageStatus::SENT,
                whatsappMessageId: $result[0]['messageId'] ?? null,
            );

            $this->onSuccess();
        } catch (Throwable $e) {
            Log::error('WhatsApp message failed', [
                'session_id' => $this->sessionId,
                'to' => $this->to,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            $this->updateMessageHistory(WhatsAppMessageStatus::FAILED, errorMessage: $e->getMessage());

            throw $e;
        }
    }

    protected function validateSession(): void
    {
        $session = WhatsAppSession::find($this->sessionId);

        if (! $session || ! $session->isConnected()) {
            $reason = $session ? 'Session not connected' : 'Session not found';
            $this->updateMessageHistory(WhatsAppMessageStatus::FAILED, errorMessage: $reason);

            throw new \Exception($reason);
        }
    }

    public function failed(Throwable $exception): void
    {
        $this->updateMessageHistory(WhatsAppMessageStatus::FAILED, errorMessage: $exception->getMessage());

        Log::error('WhatsApp job permanently failed', [
            'session_id' => $this->sessionId,
            'to' => $this->to,
            'error' => $exception->getMessage(),
        ]);
    }

    protected function onSuccess(): void
    {
        if ($this->studentId) {
            $this->createWhatsAppProgressRecord();
        }

        if ($this->metadata['disconnection_id'] ?? null) {
            $this->updateDisconnectionRecord();
        }
    }

    protected function createWhatsAppProgressRecord(): void
    {
        $student = Student::find($this->studentId);

        if (! $student) {
            return;
        }

        $date = now()->format('Y-m-d');
        $existingProgress = $student->progresses()->where('date', $date)->first();

        if ($existingProgress) {
            if ($existingProgress->comment !== 'message_sent') {
                $existingProgress->update(['comment' => 'message_sent_whatsapp']);
            }

            return;
        }

        $student->progresses()->create([
            'created_by' => $this->metadata['sender_user_id'] ?? null,
            'date' => $date,
            'comment' => 'message_sent_whatsapp',
        ]);
    }

    protected function updateDisconnectionRecord(): void
    {
        $disconnection = StudentDisconnection::find($this->metadata['disconnection_id']);

        if (! $disconnection) {
            return;
        }

        $messageResponseType = MessageResponseStatus::tryFrom($this->metadata['message_response_type'] ?? '');

        $updateData = [
            'contact_date' => now()->format('Y-m-d'),
            'message_response' => $messageResponseType,
        ];

        if ($messageResponseType === MessageResponseStatus::ReminderMessage) {
            $updateData['reminder_message_date'] = now()->format('Y-m-d');
        } elseif ($messageResponseType === MessageResponseStatus::WarningMessage) {
            $updateData['warning_message_date'] = now()->format('Y-m-d');
        }

        $disconnection->update($updateData);
    }

    protected function updateMessageHistory(
        WhatsAppMessageStatus $status,
        ?string $whatsappMessageId = null,
        ?string $errorMessage = null
    ): void {
        try {
            $messageHistory = WhatsAppMessageHistory::query()
                ->where('session_id', $this->sessionId)
                ->where('recipient_phone', $this->to)
                ->where('message_content', $this->message)
                ->where('status', WhatsAppMessageStatus::QUEUED)
                ->latest()
                ->first();

            if (! $messageHistory) {
                return;
            }

            match ($status) {
                WhatsAppMessageStatus::SENT => $messageHistory->markAsSent($whatsappMessageId),
                WhatsAppMessageStatus::FAILED => $messageHistory->markAsFailed($errorMessage),
                default => $messageHistory->update(['status' => $status]),
            };
        } catch (Throwable $e) {
            Log::warning('Failed to update message history', ['error' => $e->getMessage()]);
        }
    }
}
