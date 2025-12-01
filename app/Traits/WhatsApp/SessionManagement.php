<?php

namespace App\Traits\WhatsApp;

use App\Enums\WhatsAppConnectionStatus;
use App\Models\WhatsAppSession;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

trait SessionManagement
{
    public function createSession(string $sessionId): array
    {
        try {
            $formattedSessionId = $this->formatSessionId($sessionId);

            $response = Http::withHeaders([
                'X-Master-Key' => $this->getMasterKey(),
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/api/v1/sessions", [
                'sessionId' => $formattedSessionId,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                Cache::put("whatsapp_token_{$sessionId}", $data['token'], now()->addHours(24));

                return $data;
            }

            throw new \Exception("HTTP {$response->status()}: ".$response->body());
        } catch (\Exception $e) {
            Log::error('Failed to create WhatsApp session', [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function getAllSessions(): array
    {
        try {
            $response = Http::get("{$this->baseUrl}/api/v1/sessions");

            if ($response->successful()) {
                return $response->json();
            }

            throw new \Exception("HTTP {$response->status()}: ".$response->body());
        } catch (\Exception $e) {
            Log::error('Failed to get all WhatsApp sessions', [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function getSessionStatus(string $sessionId): array
    {
        try {
            $formattedSessionId = $this->formatSessionId($sessionId);

            $response = Http::get("{$this->baseUrl}/api/v1/sessions");

            if ($response->successful()) {
                $sessions = $response->json();
                $session = collect($sessions)->firstWhere('sessionId', $formattedSessionId);

                if (! $session) {
                    throw new \Exception("Session {$sessionId} not found");
                }

                // Update cached token if it exists in the response
                if (isset($session['token']) && ! empty($session['token'])) {
                    $currentToken = Cache::get("whatsapp_token_{$sessionId}");
                    $newToken = $session['token'];

                    // Only update cache if token has changed
                    if ($currentToken !== $newToken) {
                        Cache::put("whatsapp_token_{$sessionId}", $newToken, now()->addHours(24));
                        Log::info('WhatsApp session token updated', [
                            'session_id' => $sessionId,
                            'old_token_exists' => ! empty($currentToken),
                        ]);
                    }
                }

                return $session;
            }

            throw new \Exception("HTTP {$response->status()}: ".$response->body());
        } catch (\Exception $e) {
            Log::error('Failed to get WhatsApp session status', [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function waitForConnection(string $sessionId, int $maxAttempts = 60): array
    {
        $attempts = 0;

        while ($attempts < $maxAttempts) {
            try {
                $status = $this->getSessionStatus($sessionId);

                if ($status['status'] === 'CONNECTED') {
                    Log::info('WhatsApp session connected', ['session_id' => $sessionId]);

                    return $status;
                }

                sleep(2);
                $attempts++;
            } catch (\Exception $e) {
                Log::error('Error checking connection status', [
                    'session_id' => $sessionId,
                    'attempt' => $attempts,
                    'error' => $e->getMessage(),
                ]);
                $attempts++;
            }
        }

        throw new \Exception("Session {$sessionId} failed to connect after {$maxAttempts} attempts");
    }

    /**
     * Start a WhatsApp session asynchronously (non-blocking).
     * This method returns immediately after creating/checking the session.
     * Frontend polling will handle QR code retrieval.
     */
    public function startSessionAsync(WhatsAppSession $session): array
    {
        try {
            $sessionId = $session->id;

            // First check if session already exists on the API
            try {
                Log::info('Checking if session already exists', ['session_id' => $sessionId]);
                $existingStatus = $this->getSessionStatus($sessionId);

                Log::info('Found existing session on API', [
                    'session_id' => $sessionId,
                    'status' => $existingStatus['status'] ?? 'unknown',
                    'has_qr' => ! empty($existingStatus['qr']),
                ]);

                // Update session from existing API status
                $this->updateSessionFromApiStatus($session, $existingStatus);

                return $existingStatus;
            } catch (\Exception $e) {
                Log::info('Session not found on API, creating new one', [
                    'session_id' => $sessionId,
                    'error' => $e->getMessage(),
                ]);
            }

            // Session doesn't exist, create it
            $result = $this->createSession($sessionId);

            // Set to CREATING status and return immediately - let polling do the rest
            $session->update([
                'status' => WhatsAppConnectionStatus::CREATING,
                'session_data' => $result,
                'last_activity_at' => now(),
            ]);

            Log::info('Session created, polling will handle QR retrieval', [
                'session_id' => $sessionId,
                'initial_status' => $result['status'] ?? 'unknown',
            ]);

            return $result;
        } catch (\Exception $e) {
            Log::error('Failed to start WhatsApp session async', [
                'session_id' => $session->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Update session model from API status response.
     * Centralizes status update logic with strict connection verification.
     */
    public function updateSessionFromApiStatus(WhatsAppSession $session, array $apiResult): void
    {
        $apiStatus = strtoupper($apiResult['status'] ?? 'PENDING');
        $modelStatus = WhatsAppConnectionStatus::fromApiStatus($apiStatus);

        Log::info('Updating session from API status', [
            'session_id' => $session->id,
            'api_status' => $apiStatus,
            'has_me' => isset($apiResult['me']),
            'has_token' => ! empty($apiResult['token']),
            'has_qr' => ! empty($apiResult['qr']),
        ]);

        // Handle connected status - with strict verification
        if ($apiStatus === 'CONNECTED') {
            // Verify real connection by checking for 'me' field or token
            $hasPhoneInfo = ! empty($apiResult['me']) || ! empty($apiResult['user']);
            $hasToken = ! empty($apiResult['token']);
            $hasQr = ! empty($apiResult['qr']);

            // If QR is still present or no phone info/token, not truly connected
            if ($hasQr || (! $hasPhoneInfo && ! $hasToken)) {
                Log::warning('API reports CONNECTED but verification failed', [
                    'session_id' => $session->id,
                    'has_phone_info' => $hasPhoneInfo,
                    'has_token' => $hasToken,
                    'has_qr' => $hasQr,
                ]);
                $modelStatus = WhatsAppConnectionStatus::PENDING;
            } else {
                $session->markAsConnected($apiResult);

                return;
            }
        }

        // Clean up QR code data if present
        $qrCode = null;
        if (! empty($apiResult['qr'])) {
            $qrCode = $this->cleanQrCodeData($apiResult['qr']);
        }

        $session->update([
            'status' => $modelStatus,
            'qr_code' => $qrCode,
            'session_data' => $apiResult,
            'last_activity_at' => now(),
        ]);
    }

    /**
     * Start a WhatsApp session synchronously (blocking).
     * This waits for QR code generation before returning.
     * Use startSessionAsync() for non-blocking behavior.
     *
     * @deprecated Use startSessionAsync() for better UX
     */
    public function startSession(WhatsAppSession $session): array
    {
        try {
            $sessionId = $session->id;

            // First check if session already exists on the API
            try {
                Log::info('Checking if session already exists', ['session_id' => $sessionId]);
                $existingStatus = $this->getSessionStatus($sessionId);

                Log::info('Found existing session on API', [
                    'session_id' => $sessionId,
                    'status' => $existingStatus['status'] ?? 'unknown',
                    'has_qr' => ! empty($existingStatus['qr']),
                ]);

                // Use existing session instead of creating new one
                $result = $existingStatus;
            } catch (\Exception $e) {
                Log::info('Session not found on API, creating new one', [
                    'session_id' => $sessionId,
                    'error' => $e->getMessage(),
                ]);

                // Session doesn't exist, create it
                $result = $this->createSession($sessionId);
            }

            // Wait for QR code if we don't have one yet (reduced to 5 attempts for faster feedback)
            $attempts = 0;
            $maxAttempts = 5;

            while ($attempts < $maxAttempts && empty($result['qr']) && $result['status'] !== 'CONNECTED') {
                Log::info('Waiting for QR code', [
                    'session_id' => $sessionId,
                    'attempt' => $attempts,
                    'current_status' => $result['status'] ?? 'unknown',
                ]);

                sleep(1);
                $attempts++;

                try {
                    $result = $this->getSessionStatus($sessionId);
                    if (! empty($result['qr']) || $result['status'] === 'CONNECTED') {
                        break;
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to get session status during wait', [
                        'session_id' => $sessionId,
                        'attempt' => $attempts,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Update session from API status
            $this->updateSessionFromApiStatus($session, $result);

            Log::info('Session started successfully', [
                'session_id' => $sessionId,
                'final_status' => $result['status'] ?? 'unknown',
                'has_qr' => ! empty($result['qr']),
            ]);

            return $result;
        } catch (\Exception $e) {
            Log::error('Failed to start WhatsApp session', [
                'session_id' => $session->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function deleteSession(string $sessionId): array
    {
        try {
            $formattedSessionId = $this->formatSessionId($sessionId);

            // Get session token - try cache first, then session data, then API
            $token = $this->getSessionToken($sessionId);

            // If no token in cache/DB, try to get fresh session data from API
            if (! $token) {
                try {
                    $sessionStatus = $this->getSessionStatus($sessionId);
                    $token = $sessionStatus['token'] ?? null;
                    if ($token) {
                        Cache::put("whatsapp_token_{$sessionId}", $token, now()->addHours(24));
                    }
                } catch (\Exception $e) {
                    Log::warning('Could not get session token from API', ['session_id' => $sessionId]);
                }
            }

            // Try to delete with session token
            if ($token) {
                $response = Http::withHeaders([
                    'Authorization' => "Bearer {$token}",
                    'Content-Type' => 'application/json',
                ])->delete("{$this->baseUrl}/api/v1/sessions/{$formattedSessionId}");

                if ($response->successful()) {
                    Cache::forget("whatsapp_token_{$sessionId}");
                    Log::info('WhatsApp session deleted', ['session_id' => $sessionId]);

                    return $response->json();
                }

                Log::warning('Session deletion with token failed', [
                    'session_id' => $sessionId,
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);
            }

            // If we reach here, token deletion failed or no token available
            // This is OK - session might already be deleted or disconnected
            Log::info('WhatsApp session deletion skipped - session may already be disconnected', [
                'session_id' => $sessionId,
            ]);

            Cache::forget("whatsapp_token_{$sessionId}");

            return ['status' => 'success', 'message' => 'Session marked as disconnected'];
        } catch (\Exception $e) {
            Log::error('Failed to delete WhatsApp session', [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function logout(WhatsAppSession $session): array
    {
        try {
            $sessionId = $session->id;

            // Always mark as disconnected first (in case API call fails)
            $session->markAsDisconnected();

            // Try to delete from API
            $result = $this->deleteSession($sessionId);

            Log::info('WhatsApp session logged out successfully', ['session_id' => $sessionId]);

            return $result;
        } catch (\Exception $e) {
            Log::error('Failed to logout WhatsApp session', [
                'session_id' => $session->id,
                'error' => $e->getMessage(),
            ]);

            // Session is already marked as disconnected, so we can still consider it "logged out"
            // Return a success response even if API call failed
            return ['status' => 'success', 'message' => 'Session marked as disconnected'];
        }
    }

    protected function getSessionToken(string $sessionId): ?string
    {
        // First try to get from cache
        $token = Cache::get("whatsapp_token_{$sessionId}");

        if ($token) {
            return $token;
        }

        // If not in cache, try to get from session data
        $session = WhatsAppSession::query()->find($sessionId);
        if ($session && $session->session_data && isset($session->session_data['token'])) {
            $token = $session->session_data['token'];
            // Cache it for future use
            Cache::put("whatsapp_token_{$sessionId}", $token, now()->addHours(24));

            return $token;
        }

        // If still no token, try to refresh from API
        try {
            $sessionStatus = $this->getSessionStatus($sessionId);
            if (isset($sessionStatus['token']) && ! empty($sessionStatus['token'])) {
                $token = $sessionStatus['token'];
                Cache::put("whatsapp_token_{$sessionId}", $token, now()->addHours(24));

                // Also update the session data in the database
                if ($session) {
                    $sessionData = $session->session_data ?? [];
                    $sessionData['token'] = $token;
                    $session->update(['session_data' => $sessionData]);
                }

                return $token;
            }
        } catch (\Exception $e) {
            Log::warning('Could not refresh token from API', [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }
}
