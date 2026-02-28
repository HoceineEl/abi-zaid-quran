<?php

namespace App\Traits\WhatsApp;

use App\Enums\WhatsAppConnectionStatus;
use App\Models\WhatsAppSession;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

trait SessionManagement
{
    public function createInstance(string $instanceName): array
    {
        try {
            $response = Http::withHeaders($this->evolutionHeaders())
                ->timeout(15)
                ->post("{$this->baseUrl}/instance/create", [
                    'instanceName' => $instanceName,
                    'qrcode' => true,
                    'integration' => 'WHATSAPP-BAILEYS',
                ]);

            if ($response->successful()) {
                return $response->json();
            }

            throw new \RuntimeException("HTTP {$response->status()}: ".$response->body());
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
        try {
            $response = Http::withHeaders($this->evolutionHeaders())
                ->timeout(15)
                ->get("{$this->baseUrl}/instance/connect/{$instanceName}");

            if ($response->successful()) {
                return $response->json();
            }

            throw new \RuntimeException("HTTP {$response->status()}: ".$response->body());
        } catch (\Exception $e) {
            Log::error('Failed to connect WhatsApp instance', [
                'instance' => $instanceName,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function getAllInstances(): array
    {
        try {
            $response = Http::withHeaders($this->evolutionHeaders())
                ->timeout(15)
                ->get("{$this->baseUrl}/instance/fetchInstances");

            if ($response->successful()) {
                return $response->json();
            }

            throw new \RuntimeException("HTTP {$response->status()}: ".$response->body());
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
            $response = Http::withHeaders($this->evolutionHeaders())
                ->timeout(15)
                ->get("{$this->baseUrl}/instance/connectionState/{$instanceName}");

            if ($response->successful()) {
                return $response->json();
            }

            throw new \RuntimeException("HTTP {$response->status()}: ".$response->body());
        } catch (\Exception $e) {
            Log::error('Failed to get WhatsApp instance status', [
                'instance' => $instanceName,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
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

        $qrCode = null;
        if ($modelStatus->canShowQrCode()) {
            try {
                $connectResult = $this->connectInstance($session->name);
                $base64Qr = $connectResult['base64'] ?? null;
                if ($base64Qr) {
                    $qrCode = $this->cleanQrCodeData($base64Qr);
                }
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
            $response = Http::withHeaders($this->evolutionHeaders())
                ->timeout(15)
                ->delete("{$this->baseUrl}/instance/delete/{$instanceName}");

            if ($response->successful()) {
                Log::info('WhatsApp instance deleted', ['instance' => $instanceName]);

                return $response->json();
            }

            throw new \RuntimeException("HTTP {$response->status()}: ".$response->body());
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
            $response = Http::withHeaders($this->evolutionHeaders())
                ->timeout(15)
                ->delete("{$this->baseUrl}/instance/logout/{$instanceName}");

            if ($response->successful()) {
                Log::info('WhatsApp instance logged out', ['instance' => $instanceName]);

                return $response->json();
            }

            throw new \RuntimeException("HTTP {$response->status()}: ".$response->body());
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
