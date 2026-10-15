<?php

namespace App\Services;

use App\Enums\WhatsAppMessageStatus;
use App\Jobs\SendWhatsAppMessageJob;
use App\Models\WhatsAppMessageHistory;
use App\Models\WhatsAppSession;
use App\Traits\WhatsApp\SessionManagement;
use App\Traits\WhatsApp\UtilityOperations;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    use SessionManagement, UtilityOperations;

    protected Client $client;

    protected string $baseUrl;

    protected ?string $username;

    protected ?string $password;

    protected ?string $apiUrl;

    protected ?string $accessToken;

    public function __construct()
    {
        $this->client = new Client;

        // Legacy WhatsApp Cloud API (for templates via Core.php)
        $this->apiUrl = config('services.whatsapp.api_url');
        $this->accessToken = config('services.whatsapp.access_token');

        $this->baseUrl = rtrim(config('whatsapp.api_url', 'http://localhost:3000'), '/');
        $this->username = config('whatsapp.username');
        $this->password = config('whatsapp.password');

        if (! $this->hasCredentials()) {
            Log::warning('WhatsApp API credentials are not configured. Check whatsapp.username and whatsapp.password config.');
        }
    }

    protected function hasCredentials(): bool
    {
        return ! empty($this->username) && ! empty($this->password);
    }

    protected function gowa(?string $instanceName = null, int $timeout = 15): PendingRequest
    {
        if (! $this->hasCredentials()) {
            throw new \RuntimeException('WhatsApp API credentials are not configured.');
        }

        return Http::baseUrl($this->baseUrl)
            ->withBasicAuth($this->username, $this->password)
            ->acceptJson()
            ->timeout($timeout)
            ->when($instanceName, fn (PendingRequest $request) => $request->withHeaders([
                'X-Device-Id' => $this->deviceId($instanceName),
            ]));
    }

    public function deviceId(string $instanceName): string
    {
        return 'session-'.substr(md5($instanceName), 0, 16);
    }

    protected function results(Response $response): mixed
    {
        if ($response->failed() || $response->json('code') !== 'SUCCESS') {
            throw new \RuntimeException("HTTP {$response->status()}: ".$response->body());
        }

        return $response->json('results');
    }

    protected function toJid(string $phone): string
    {
        return str_contains($phone, '@') ? $phone : "{$phone}@s.whatsapp.net";
    }

    protected function simulateTyping(string $instanceName, string $phone, int $delayMs): void
    {
        if ($delayMs <= 0) {
            return;
        }

        try {
            $this->gowa($instanceName, 5)->post('/send/chat-presence', [
                'phone' => $this->toJid($phone),
                'action' => 'start',
            ]);
        } catch (\Exception) {
            return;
        }

        usleep($delayMs * 1000);
    }

    protected function computeTypingDelayMs(?string $text = null): int
    {
        if (! config('whatsapp.typing.enabled', true)) {
            return 0;
        }

        $minMs = (int) config('whatsapp.typing.min_ms', 1200);
        $maxMs = (int) config('whatsapp.typing.max_ms', 5000);
        $perCharMs = (int) config('whatsapp.typing.per_char_ms', 35);
        $jitterMs = (int) config('whatsapp.typing.jitter_ms', 350);

        $base = mb_strlen((string) $text) * $perCharMs;
        $jitter = $jitterMs > 0 ? rand(-$jitterMs, $jitterMs) : 0;

        return max($minMs, min($maxMs, $base + $jitter));
    }

    public function sendMessage($student, ?string $message = null): array
    {
        try {
            $response = $this->client->post($this->apiUrl, [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'messaging_product' => 'whatsapp',
                    'to' => 'whatsapp:'.str_replace('+', '', phone($student->phone, 'MA')->formatE164()),
                    'type' => 'template',
                    'template' => [
                        'name' => 'alert',
                        'language' => ['code' => 'ar'],
                        'components' => [
                            [
                                'type' => 'body',
                                'parameters' => [
                                    ['type' => 'text', 'text' => $student->name],
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                return json_decode($e->getResponse()->getBody()->getContents(), true);
            }

            return ['error' => $e->getMessage()];
        }
    }

    public function sendCustomMessage($student, ?string $message = null): array
    {
        try {
            $response = $this->client->post($this->apiUrl, [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'messaging_product' => 'whatsapp',
                    'to' => 'whatsapp:'.str_replace('+', '', phone($student->phone, 'MA')->formatE164()),
                    'type' => 'template',
                    'template' => [
                        'name' => 'absence',
                        'language' => ['code' => 'ar'],
                        'components' => [
                            [
                                'type' => 'body',
                                'parameters' => [
                                    ['type' => 'text', 'text' => $student->name],
                                    ['type' => 'text', 'text' => $message],
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                return json_decode($e->getResponse()->getBody()->getContents(), true);
            }

            return ['error' => $e->getMessage()];
        }
    }

    public function sendTextMessage(string $instanceName, string $to, string $message): array
    {
        $formattedPhone = str_replace('+', '', phone($to, 'MA')->formatE164());
        $typingDelay = $this->computeTypingDelayMs($message);

        try {
            $this->simulateTyping($instanceName, $formattedPhone, $typingDelay);

            $result = $this->results($this->gowa($instanceName)->post('/send/message', [
                'phone' => $this->toJid($formattedPhone),
                'message' => $message,
            ]));

            Log::info('WhatsApp message sent', [
                'instance' => $instanceName,
                'to' => $formattedPhone,
                'message_id' => $result['message_id'] ?? null,
            ]);

            return $result;
        } catch (\Exception $e) {
            Log::error('Failed to send WhatsApp message', [
                'instance' => $instanceName,
                'to' => $to,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function sendMediaMessage(string $instanceName, string $to, string $mediaUrl, string $mediaType = 'image', ?string $caption = null): array
    {
        $formattedPhone = str_replace('+', '', phone($to, 'MA')->formatE164());
        $typingDelay = $this->computeTypingDelayMs($caption);

        $mediaField = match ($mediaType) {
            'image', 'video', 'audio' => $mediaType,
            default => 'file',
        };

        try {
            $this->simulateTyping($instanceName, $formattedPhone, $typingDelay);

            $result = $this->results($this->gowa($instanceName, 60)->asMultipart()->post("/send/{$mediaField}", array_filter([
                'phone' => $this->toJid($formattedPhone),
                "{$mediaField}_url" => $mediaUrl,
                'caption' => $mediaField === 'audio' ? null : $caption,
            ])));

            Log::info('WhatsApp media message sent', [
                'instance' => $instanceName,
                'to' => $formattedPhone,
                'media_type' => $mediaType,
                'message_id' => $result['message_id'] ?? null,
            ]);

            return $result;
        } catch (\Exception $e) {
            Log::error('Failed to send WhatsApp media message', [
                'instance' => $instanceName,
                'to' => $to,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function queueMessage(WhatsAppSession $session, string $to, string $message, string $type = 'text'): void
    {
        $formattedPhone = str_replace('+', '', phone($to, 'MA')->formatE164());

        WhatsAppMessageHistory::create([
            'session_id' => $session->id,
            'sender_user_id' => auth()->id(),
            'recipient_phone' => $formattedPhone,
            'message_type' => $type,
            'message_content' => $message,
            'status' => WhatsAppMessageStatus::QUEUED,
        ]);

        $delay = SendWhatsAppMessageJob::getStaggeredDelay($session->id);

        SendWhatsAppMessageJob::dispatch(
            $session->id,
            $formattedPhone,
            $message,
            $type,
        )->delay(now()->addSeconds($delay));

        Log::info('WhatsApp message queued for delivery', [
            'session_id' => $session->id,
            'to' => $formattedPhone,
            'message_type' => $type,
        ]);
    }

    public function refreshQrCode(WhatsAppSession $session): void
    {
        try {
            $base64Qr = $this->connectInstance($session->name)['base64'] ?? null;

            if ($base64Qr) {
                $session->updateQrCode($base64Qr);

                return;
            }

            throw new \RuntimeException('No QR code available for instance');
        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to refresh QR code: '.$e->getMessage(), previous: $e);
        }
    }
}
