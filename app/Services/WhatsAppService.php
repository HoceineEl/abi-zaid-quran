<?php

namespace App\Services;

use App\Enums\WhatsAppMessageStatus;
use App\Models\WhatsAppMessageHistory;
use App\Models\WhatsAppSession;
use App\Traits\WhatsApp\SessionManagement;
use App\Traits\WhatsApp\UtilityOperations;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    use SessionManagement, UtilityOperations;

    protected $client;

    protected $apiUrl;

    protected $accessToken;

    protected string $baseUrl;

    protected ?string $masterKey;

    public function __construct()
    {
        $this->client = new Client;
        // Legacy WhatsApp API (for templates)
        $this->apiUrl = config('services.whatsapp.api_url');
        $this->accessToken = config('services.whatsapp.access_token');

        // WhatsApp Web API configuration
        $this->baseUrl = config('whatsapp.api_url', 'http://localhost:3000');
        $this->masterKey = config('whatsapp.api_token');

        if (is_null($this->masterKey)) {
            Log::warning('WhatsApp API token is null. Check whatsapp.api_token config.');
        }
    }

    /**
     * Get the session ID with appropriate suffix for local development
     */
    protected function formatSessionId($sessionId): string
    {
        // Add suffix for local development environment
        if (app()->environment('local')) {
            return $sessionId.'_local_abizaid';
        }

        return (string) $sessionId;
    }

    protected function getMasterKey(): string
    {
        if (is_null($this->masterKey)) {
            throw new Exception('WhatsApp API token is not configured.');
        }

        return $this->masterKey;
    }

    public function sendMessage($student, $message = null)
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
                        'language' => [
                            'code' => 'ar',
                        ],
                        'components' => [
                            [
                                'type' => 'body',
                                'parameters' => [
                                    [
                                        'type' => 'text',
                                        'text' => $student->name,
                                    ],
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

    public function sendVoiceMessage($student, $audioUrl)
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
                    'type' => 'audio',
                    'audio' => [
                        'link' => $audioUrl,
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

    public function sendCustomMessage($student, $message = null)
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
                        'language' => [
                            'code' => 'ar',
                        ],
                        'components' => [
                            [
                                'type' => 'body',
                                'parameters' => [
                                    [
                                        'type' => 'text',
                                        'text' => $student->name,
                                    ],
                                    [
                                        'type' => 'text',
                                        'text' => $message,
                                    ],
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

    // WhatsApp Web API Methods - now using SessionManagement and UtilityOperations traits

    // getSessionStatus method is now provided by SessionManagement trait

    // startSession method is now provided by SessionManagement trait

    public function sendTextMessage(string $sessionId, string $to, string $message): array
    {
        $formattedSessionId = $this->formatSessionId($sessionId);
        $token = Cache::get("whatsapp_token_{$sessionId}");

        if (! $token) {
            throw new Exception("Session token not found for session {$sessionId}");
        }

        // Format phone number using the phone helper (remove + sign)
        $formattedPhone = str_replace('+', '', phone($to, 'MA')->formatE164());

        try {
            $messageData = [
                [
                    'recipient_type' => 'individual',
                    'to' => $formattedPhone,
                    'type' => 'text',
                    'text' => [
                        'body' => $message,
                    ],
                ],
            ];

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$token}",
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/api/v1/messages?sessionId={$formattedSessionId}", $messageData);

            if ($response->successful()) {
                $result = $response->json();

                Log::info('WhatsApp message sent', [
                    'session_id' => $sessionId,
                    'to' => $formattedPhone,
                    'message_id' => $result[0]['messageId'] ?? null,
                ]);

                return $result;
            }

            throw new Exception("HTTP {$response->status()}: ".$response->body());
        } catch (Exception $e) {
            Log::error('Failed to send WhatsApp message', [
                'session_id' => $sessionId,
                'to' => $to,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function queueMessage(WhatsAppSession $session, string $to, string $message, string $type = 'text'): void
    {
        // Format phone number using the phone helper (remove + sign)
        $formattedPhone = str_replace('+', '', phone($to, 'MA')->formatE164());

        // Store message history
        WhatsAppMessageHistory::create([
            'session_id' => $session->id,
            'sender_user_id' => auth()->id(),
            'recipient_phone' => $formattedPhone,
            'message_type' => $type,
            'message_content' => $message,
            'status' => WhatsAppMessageStatus::QUEUED,
        ]);

        Log::info('WhatsApp message queued for delivery', [
            'session_id' => $session->id,
            'to' => $formattedPhone,
            'message_type' => $type,
        ]);
    }

    // deleteSession method is now provided by SessionManagement trait

    // getSessionToken method is now provided by SessionManagement trait

    public function refreshQrCode(WhatsAppSession $session): void
    {
        try {
            // First try to get a fresh QR code by calling the status endpoint
            $result = $this->getSessionStatus($session->id);

            if (isset($result['qr']) && ! empty($result['qr'])) {
                $session->updateQrCode($result['qr']);

                return;
            }

            // If no QR code in status, try to trigger a refresh by requesting QR generation
            try {
                $response = Http::withHeaders([
                    'X-Master-Key' => $this->getMasterKey(),
                    'Content-Type' => 'application/json',
                ])->post("{$this->baseUrl}/api/v1/sessions/{$session->id}/qr");

                if ($response->successful()) {
                    $qrData = $response->json();
                    if (isset($qrData['qr']) && ! empty($qrData['qr'])) {
                        $session->updateQrCode($qrData['qr']);

                        return;
                    }
                }
            } catch (Exception $qrException) {
                Log::warning('QR refresh endpoint failed', [
                    'session_id' => $session->id,
                    'error' => $qrException->getMessage(),
                ]);
            }

            throw new Exception('No QR code available for session');
        } catch (Exception $e) {
            throw new Exception('Failed to refresh QR code: '.$e->getMessage());
        }
    }

    // logout method is now provided by SessionManagement trait
}
