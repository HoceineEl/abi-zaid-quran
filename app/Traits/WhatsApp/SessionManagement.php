<?php

namespace App\Traits\WhatsApp;

use App\Enums\WhatsAppConnectionStatus;
use App\Models\WhatsAppSession;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

trait SessionManagement
{
    public function createInstance(string $instanceName): array
    {
        try {
            return $this->results($this->gowa()->post('/devices', [
                'device_id' => $this->deviceId($instanceName),
            ]));
        } catch (\Exception $e) {
            Log::error('Failed to create WhatsApp instance', [
                'instance' => $instanceName,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function connectInstance(string $instanceName): array
    {
        $cacheKey = $this->qrCacheKey($instanceName);

        try {
            $cached = Cache::get($cacheKey);
            if ($cached) {
                return $cached;
            }

            $login = $this->results($this->gowa()->get('/devices/'.$this->deviceId($instanceName).'/login'));
            $qrPath = parse_url($login['qr_link'] ?? '', PHP_URL_PATH);

            $result = [
                ...$login,
                'base64' => $qrPath ? $this->fetchQrImage($qrPath) : null,
            ];

            if ($result['base64']) {
                Cache::put($cacheKey, $result, now()->addSeconds(max(5, ($login['qr_duration'] ?? 30) - 5)));
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('Failed to connect WhatsApp instance', [
                'instance' => $instanceName,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    protected function qrCacheKey(string $instanceName): string
    {
        return 'whatsapp_qr_'.$this->deviceId($instanceName);
    }

    protected function fetchQrImage(string $path): ?string
    {
        $response = $this->gowa()->accept('image/png')->get($path);

        return $response->successful()
            ? 'data:image/png;base64,'.base64_encode($response->body())
            : null;
    }

    public function getAllInstances(): array
    {
        try {
            return collect($this->results($this->gowa()->get('/devices')) ?? [])
                ->map(fn (array $device) => $this->toInstanceStatus($device['id'] ?? 'unknown', $device))
                ->all();
        } catch (\Exception $e) {
            Log::error('Failed to get all WhatsApp instances', [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function getInstanceStatus(string $instanceName): array
    {
        try {
            $device = $this->results($this->gowa()->get('/devices/'.$this->deviceId($instanceName)));

            return $this->toInstanceStatus($instanceName, $device);
        } catch (\Exception $e) {
            Log::error('Failed to get WhatsApp instance status', [
                'instance' => $instanceName,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    protected function toInstanceStatus(string $instanceName, array $device): array
    {
        return [
            'instance' => [
                'instanceName' => $instanceName,
                'state' => match ($device['state'] ?? null) {
                    'logged_in' => 'open',
                    'connected', 'connecting' => 'connecting',
                    default => 'close',
                },
                'ownerJid' => $device['jid'] ?? null,
            ],
            'device' => $device,
        ];
    }

    protected function resolveApiState(array $apiResult): string
    {
        return $apiResult['instance']['state'] ?? $apiResult['state'] ?? 'close';
    }

    /**
     * @deprecated Use getInstanceStatus() instead
     */
    public function getSessionStatus(string $sessionId): array
    {
        return $this->getInstanceStatus($sessionId);
    }

    public function startSessionAsync(WhatsAppSession $session): array
    {
        try {
            $instanceName = $session->name;

            try {
                $connectionState = $this->getInstanceStatus($instanceName);
                $state = $this->resolveApiState($connectionState);
                $modelStatus = WhatsAppConnectionStatus::fromApiStatus($state);

                Log::info('Found existing instance', [
                    'instance' => $instanceName,
                    'state' => $state,
                ]);

                // Already connected — just sync DB and return
                if ($modelStatus === WhatsAppConnectionStatus::CONNECTED) {
                    $this->updateSessionFromApiStatus($session, $connectionState);

                    return $connectionState;
                }

                // Instance exists but is disconnected — reconnect to get a fresh QR code
                $connectResult = $this->connectInstance($instanceName);
                $qrCode = $connectResult['base64'] ?? null;

                $session->update([
                    'status' => $qrCode ? WhatsAppConnectionStatus::PENDING : WhatsAppConnectionStatus::CONNECTING,
                    'qr_code' => $qrCode ? $this->cleanQrCodeData($qrCode) : null,
                    'last_activity_at' => now(),
                ]);

                Log::info('Reconnected existing instance, got QR', [
                    'instance' => $instanceName,
                    'has_qr' => ! empty($qrCode),
                ]);

                return $connectResult;
            } catch (\Exception $e) {
                Log::info('Instance not found, creating new one', [
                    'instance' => $instanceName,
                    'error' => $e->getMessage(),
                ]);
            }

            $result = $this->createInstance($instanceName);
            $connectResult = $this->connectInstance($instanceName);
            $qrCode = $connectResult['base64'] ?? null;

            $session->update([
                'status' => $qrCode ? WhatsAppConnectionStatus::PENDING : WhatsAppConnectionStatus::CREATING,
                'qr_code' => $qrCode ? $this->cleanQrCodeData($qrCode) : null,
                'session_data' => $result,
                'last_activity_at' => now(),
            ]);

            Log::info('Instance created, polling will handle status updates', [
                'instance' => $instanceName,
                'has_qr' => ! empty($qrCode),
            ]);

            return $connectResult;
        } catch (\Exception $e) {
            Log::error('Failed to start WhatsApp instance async', [
                'instance' => $session->name,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function updateSessionFromApiStatus(WhatsAppSession $session, array $apiResult): void
    {
        $state = $this->resolveApiState($apiResult);
        $modelStatus = WhatsAppConnectionStatus::fromApiStatus($state);
        $wasConnected = $session->status === WhatsAppConnectionStatus::CONNECTED;
        $wasPairing = $session->status->canShowQrCode();

        Log::info('Updating session from API status', [
            'session_id' => $session->id,
            'instance' => $session->name,
            'api_state' => $state,
            'model_status' => $modelStatus->value,
        ]);

        if ($modelStatus === WhatsAppConnectionStatus::CONNECTED) {
            $session->markAsConnected($apiResult);

            return;
        }

        // A connected instance that momentarily reports a non-final state is a transient
        // blip (phone briefly offline) — never re-pair or downgrade it on a single reading.
        if ($wasConnected && $modelStatus !== WhatsAppConnectionStatus::DISCONNECTED) {
            return;
        }

        // Only mint a fresh QR while genuinely mid-pairing; calling connect on an
        // established instance forces a re-pair and drops the linked device.
        $qrCode = null;
        if ($wasPairing && $modelStatus->canShowQrCode()) {
            try {
                $connectResult = $this->connectInstance($session->name);
                $base64Qr = $connectResult['base64'] ?? null;
                $qrCode = $base64Qr ? $this->cleanQrCodeData($base64Qr) : null;
            } catch (\Exception $e) {
                Log::warning('Could not get QR code during status update', [
                    'instance' => $session->name,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $session->update([
            'status' => $modelStatus,
            'qr_code' => $qrCode,
            'session_data' => $apiResult,
            'last_activity_at' => now(),
        ]);
    }

    public function deleteInstance(string $instanceName): array
    {
        try {
            $result = $this->results($this->gowa()->delete('/devices/'.$this->deviceId($instanceName)));

            Log::info('WhatsApp instance deleted', ['instance' => $instanceName]);

            return (array) $result;
        } catch (\Exception $e) {
            Log::error('Failed to delete WhatsApp instance', [
                'instance' => $instanceName,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function logoutInstance(string $instanceName): array
    {
        try {
            $result = $this->results($this->gowa()->post('/devices/'.$this->deviceId($instanceName).'/logout'));

            Log::info('WhatsApp instance logged out', ['instance' => $instanceName]);

            return (array) $result;
        } catch (\Exception $e) {
            Log::error('Failed to logout WhatsApp instance', [
                'instance' => $instanceName,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function logout(WhatsAppSession $session): array
    {
        $instanceName = $session->name;

        $session->markAsDisconnected();
        Cache::forget($this->qrCacheKey($instanceName));

        try {
            $this->logoutInstance($instanceName);
        } catch (\Exception $e) {
            Log::warning('Logout API call failed, trying delete', [
                'instance' => $instanceName,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            $result = $this->deleteInstance($instanceName);
        } catch (\Exception $e) {
            Log::warning('Delete API call failed', [
                'instance' => $instanceName,
                'error' => $e->getMessage(),
            ]);
            $result = ['status' => 'success', 'message' => 'Session marked as disconnected'];
        }

        Log::info('WhatsApp session logged out successfully', ['instance' => $instanceName]);

        return $result;
    }

    /**
     * @deprecated Use deleteInstance() instead
     */
    public function deleteSession(string $sessionId): array
    {
        return $this->deleteInstance($sessionId);
    }
}
