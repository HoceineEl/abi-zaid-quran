<?php

namespace App\Services;

use App\Enums\WhatsAppMessageStatus;
use App\Models\WhatsAppMessageHistory;
use App\Models\WhatsAppSession;
use App\Traits\WhatsApp\SessionManagement;
use App\Traits\WhatsApp\UtilityOperations;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    use SessionManagement, UtilityOperations;

    protected Client $client;

    protected string $baseUrl;

    protected ?string $apiKey;

    protected ?string $apiUrl;

    protected ?string $accessToken;

    public function __construct()
    {
        $this->client = new Client;

        // Legacy WhatsApp Cloud API (for templates via Core.php)
        $this->apiUrl = config('services.whatsapp.api_url');
        $this->accessToken = config('services.whatsapp.access_token');

        // Evolution API configuration
        $this->baseUrl = rtrim(config('whatsapp.api_url', 'http://localhost:8080'), '/');
        $this->apiKey = config('whatsapp.api_key', '');

        if (empty($this->apiKey)) {
            Log::warning('WhatsApp API key is not configured. Check whatsapp.api_key config.');
        }
    }

    protected function getApiKey(): string
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException('WhatsApp API key is not configured.');
        }

        return $this->apiKey;
    }

    protected function evolutionHeaders(): array
    {
        return [
            'apikey' => $this->getApiKey(),
            'Content-Type' => 'application/json',
        ];
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

        try {
            $response = Http::withHeaders($this->evolutionHeaders())
                ->post("{$this->baseUrl}/message/sendText/{$instanceName}", [
                    'number' => $formattedPhone,
                    'text' => $message,
                ]);

            if ($response->successful()) {
                $result = $response->json();

                Log::info('WhatsApp message sent', [
                    'instance' => $instanceName,
                    'to' => $formattedPhone,
                    'message_id' => $result['key']['id'] ?? null,
                ]);

                return $result;
            }

            throw new \RuntimeException("HTTP {$response->status()}: ".$response->body());
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

        try {
            $payload = [
                'number' => $formattedPhone,
                'mediatype' => $mediaType,
                'media' => $mediaUrl,
            ];

            if ($caption) {
                $payload['caption'] = $caption;
            }

            $response = Http::withHeaders($this->evolutionHeaders())
                ->post("{$this->baseUrl}/message/sendMedia/{$instanceName}", $payload);

            if ($response->successful()) {
                $result = $response->json();

                Log::info('WhatsApp media message sent', [
                    'instance' => $instanceName,
                    'to' => $formattedPhone,
                    'media_type' => $mediaType,
                    'message_id' => $result['key']['id'] ?? null,
                ]);

                return $result;
            }

            throw new \RuntimeException("HTTP {$response->status()}: ".$response->body());
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

        $delay = \App\Jobs\SendWhatsAppMessageJob::getStaggeredDelay($session->id);

        \App\Jobs\SendWhatsAppMessageJob::dispatch(
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
            $instanceName = $session->name;

            $response = Http::withHeaders($this->evolutionHeaders())
                ->get("{$this->baseUrl}/instance/connect/{$instanceName}");

            if ($response->successful()) {
                $result = $response->json();
                $base64Qr = $result['base64'] ?? null;

                if ($base64Qr) {
                    $session->updateQrCode($base64Qr);

                    return;
                }
            }

            throw new \RuntimeException('No QR code available for instance');
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to refresh QR code: '.$e->getMessage());
        }
    }
}
