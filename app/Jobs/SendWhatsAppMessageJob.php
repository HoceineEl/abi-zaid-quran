<?php

namespace App\Jobs;

use App\Models\WhatsAppMessageHistory;
use App\Models\WhatsAppSession;
use App\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendWhatsAppMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $sessionId,
        public string $to,
        public string $message,
        public string $type = 'text'
    ) {}

    public function handle(WhatsAppService $whatsappService): void
    {
        try {
            $session = WhatsAppSession::find($this->sessionId);

            if (!$session || !$session->isConnected()) {
                throw new \Exception('WhatsApp session not connected');
            }

            $result = $whatsappService->sendTextMessage(
                $this->sessionId,
                $this->to,
                $this->message
            );

            // Update message history
            $messageHistory = WhatsAppMessageHistory::where('session_id', $this->sessionId)
                ->where('recipient_phone', $this->to)
                ->where('message_content', $this->message)
                ->where('status', 'queued')
                ->first();

            if ($messageHistory) {
                $messageHistory->markAsSent($result[0]['messageId'] ?? null);
            }

        } catch (\Exception $e) {
            Log::error('Failed to send WhatsApp message in job', [
                'session_id' => $this->sessionId,
                'to' => $this->to,
                'error' => $e->getMessage(),
            ]);

            // Update message history as failed
            $messageHistory = WhatsAppMessageHistory::where('session_id', $this->sessionId)
                ->where('recipient_phone', $this->to)
                ->where('message_content', $this->message)
                ->where('status', 'queued')
                ->first();

            if ($messageHistory) {
                $messageHistory->markAsFailed($e->getMessage());
            }

            throw $e;
        }
    }
}