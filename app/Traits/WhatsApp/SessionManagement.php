<?php

namespace App\Traits\WhatsApp;

use App\Enums\WhatsAppConnectionStatus;
use App\Models\WhatsAppSession;
use Exception;
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

            throw new Exception("HTTP {$response->status()}: ".$response->body());
        } catch (Exception $e) {
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

            throw new Exception("HTTP {$response->status()}: ".$response->body());
        } catch (Exception $e) {
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
                    throw new Exception("Session {$sessionId} not found");
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

            throw new Exception("HTTP {$response->status()}: ".$response->body());
        } catch (Exception $e) {
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
            } catch (Exception $e) {
                Log::error('Error checking connection status', [
                    'session_id' => $sessionId,
                    'attempt' => $attempts,
                    'error' => $e->getMessage(),
                ]);
                $attempts++;
            }
        }

        throw new Exception("Session {$sessionId} failed to connect after {$maxAttempts} attempts");
    }

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
            } catch (Exception $e) {
                Log::info('Session not found on API, creating new one', [
                    'session_id' => $sessionId,
                    'error' => $e->getMessage(),
                ]);

                // Session doesn't exist, create it
                $result = $this->createSession($sessionId);
            }

            // Wait for QR code if we don't have one yet
            $attempts = 0;
            $maxAttempts = 15; // Reduced attempts

            while ($attempts < $maxAttempts && empty($result['qr']) && $result['status'] !== 'CONNECTED') {
                Log::info('Waiting for QR code', [
                    'session_id' => $sessionId,
                    'attempt' => $attempts,
                    'current_status' => $result['status'] ?? 'unknown',
                ]);

                sleep(2);
                $attempts++;

                try {
                    $result = $this->getSessionStatus($sessionId);
                    if (! empty($result['qr']) || $result['status'] === 'CONNECTED') {
                        break;
                    }
                } catch (Exception $e) {
                    Log::warning('Failed to get session status during wait', [
                        'session_id' => $sessionId,
                        'attempt' => $attempts,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Get the final status to determine the correct status
            $finalStatus = $result;
            $status = strtoupper($finalStatus['status'] ?? 'PENDING');

            // Map API statuses to our model statuses
            $modelStatus = WhatsAppConnectionStatus::fromApiStatus($status);

            // Clean up QR code data to ensure it's valid
            $qrCode = null;
            if (! empty($finalStatus['qr'])) {
                $qrCode = $this->cleanQrCodeData($finalStatus['qr']);
                Log::info('QR code processed', [
                    'session_id' => $sessionId,
                    'raw_qr_length' => strlen($finalStatus['qr']),
                    'processed_qr_length' => strlen($qrCode ?? ''),
                    'qr_type' => str_starts_with($qrCode ?? '', 'data:') ? 'data_url' : 'other',
                ]);
            }

            $session->update([
                'status' => $modelStatus,
                'qr_code' => $qrCode,
                'session_data' => $finalStatus,
                'last_activity_at' => now(),
            ]);

            Log::info('Session started successfully', [
                'session_id' => $sessionId,
                'final_status' => $status,
                'has_qr' => ! empty($qrCode),
            ]);

            return $finalStatus;
        } catch (Exception $e) {
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
                } catch (Exception $e) {
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
        } catch (Exception $e) {
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
        } catch (Exception $e) {
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
        } catch (Exception $e) {
            Log::warning('Could not refresh token from API', [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }
}
