<?php

namespace App\Traits\WhatsApp;

use App\Models\WhatsAppSession;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

trait UtilityOperations
{
    public function refreshQrCode(WhatsAppSession $session): array
    {
        try {
            $sessionId = $session->id;

            // Get current session status
            $status = $this->getSessionStatus($sessionId);

            Log::info('Refresh QR: Got session status', [
                'session_id' => $sessionId,
                'status' => $status['status'] ?? 'unknown',
                'has_qr' => !empty($status['qr']),
                'raw_qr_preview' => substr($status['qr'] ?? '', 0, 50),
            ]);

            if (empty($status['qr'])) {
                // If no QR in current status but session exists, try to trigger QR generation
                if (in_array($status['status'] ?? '', ['GENERATING_QR', 'PENDING', 'CREATING'])) {
                    Log::info('No QR but session is in QR-generating state, waiting...', [
                        'session_id' => $sessionId,
                        'status' => $status['status'],
                    ]);

                    // Wait a bit for QR to be generated
                    for ($i = 0; $i < 5; $i++) {
                        sleep(2);
                        $status = $this->getSessionStatus($sessionId);
                        if (!empty($status['qr'])) {
                            Log::info('QR code appeared after waiting', [
                                'session_id' => $sessionId,
                                'wait_cycles' => $i + 1,
                            ]);
                            break;
                        }
                    }
                }

                if (empty($status['qr'])) {
                    throw new \Exception("No QR code available for session {$sessionId}. Status: " . ($status['status'] ?? 'unknown'));
                }
            }

            // Clean up QR code data
            $qrCode = $this->cleanQrCodeData($status['qr']);

            Log::info('QR code cleaned', [
                'session_id' => $sessionId,
                'raw_length' => strlen($status['qr']),
                'cleaned_length' => strlen($qrCode ?? ''),
                'is_data_url' => str_starts_with($qrCode ?? '', 'data:'),
            ]);

            // Update the session with new QR code
            $session->update([
                'qr_code' => $qrCode,
                'session_data' => $status,
                'last_activity_at' => now(),
            ]);

            Log::info('WhatsApp QR code refreshed successfully', [
                'session_id' => $sessionId,
                'has_qr_code' => !empty($qrCode),
            ]);

            return $status;
        } catch (\Exception $e) {
            Log::error('Failed to refresh WhatsApp QR code', [
                'session_id' => $session->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function cleanQrCodeData(?string $qrData): ?string
    {
        if (empty($qrData)) {
            return null;
        }

        // If it's already a proper data URL, return as is
        if (str_starts_with($qrData, 'data:image/')) {
            return $qrData;
        }

        try {
            // Generate QR code from the text data
            return $this->generateQrCodeImage($qrData);
        } catch (\Exception $e) {
            return null;
        }
    }

    protected function generateQrCodeImage(string $qrText): string
    {
        try {
            $renderer = new ImageRenderer(
                new RendererStyle(256, 0),
                new SvgImageBackEnd
            );

            $writer = new Writer($renderer);
            $qrCodeSvg = $writer->writeString($qrText);

            // Convert SVG to base64 data URL
            $base64 = base64_encode($qrCodeSvg);

            return 'data:image/svg+xml;base64,' . $base64;
        } catch (\Exception $e) {
            Log::error('Failed to generate QR code image', [
                'qr_text' => $qrText,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function getApiInfo(): array
    {
        try {
            $response = Http::withHeaders([
                'X-Master-Key' => $this->getMasterKey(),
            ])->get("{$this->baseUrl}/api/v1/info");

            if ($response->successful()) {
                return $response->json();
            }

            throw new \Exception("HTTP {$response->status()}: " . $response->body());
        } catch (\Exception $e) {
            Log::error('Failed to get WhatsApp API info', [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function testAuthentication(): array
    {
        try {
            $response = Http::withHeaders([
                'X-Master-Key' => $this->getMasterKey(),
            ])->get("{$this->baseUrl}/api/v1/auth");

            if ($response->successful()) {
                return $response->json();
            }

            throw new \Exception("HTTP {$response->status()}: " . $response->body());
        } catch (\Exception $e) {
            Log::error('Failed to test WhatsApp authentication', [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function checkNumber(string $number): array
    {
        try {
            $response = Http::withHeaders([
                'X-Master-Key' => $this->getMasterKey(),
            ])->get("{$this->baseUrl}/api/v1/check-number", [
                'number' => $number,
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            throw new \Exception("HTTP {$response->status()}: " . $response->body());
        } catch (\Exception $e) {
            Log::error('Failed to check WhatsApp number', [
                'number' => $number,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function getSessionGroups(string $sessionId): array
    {
        try {
            $token = $this->getSessionToken($sessionId);

            if (! $token) {
                throw new \Exception("No valid token found for session {$sessionId}");
            }

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$token}",
            ])->get("{$this->baseUrl}/api/v1/sessions/{$sessionId}/groups");

            if ($response->successful()) {
                $data = $response->json();
                $groups = $data['groups'] ?? [];

                Log::info('WhatsApp groups retrieved', [
                    'session_id' => $sessionId,
                    'groups_count' => count($groups),
                ]);

                return $groups;
            }

            throw new \Exception("HTTP {$response->status()}: " . $response->body());
        } catch (\Exception $e) {
            Log::error('Failed to get WhatsApp session groups', [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function getGroupDetails(string $sessionId, string $groupId): array
    {
        try {
            $token = $this->getSessionToken($sessionId);

            if (! $token) {
                throw new \Exception("No valid token found for session {$sessionId}");
            }

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$token}",
            ])->get("{$this->baseUrl}/api/v1/sessions/{$sessionId}/groups/{$groupId}");

            if ($response->successful()) {
                $data = $response->json();

                Log::info('WhatsApp group details retrieved', [
                    'session_id' => $sessionId,
                    'group_id' => $groupId,
                ]);

                return $data['group'] ?? $data;
            }

            throw new \Exception("HTTP {$response->status()}: " . $response->body());
        } catch (\Exception $e) {
            Log::error('Failed to get WhatsApp group details', [
                'session_id' => $sessionId,
                'group_id' => $groupId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function getGroupParticipants(string $sessionId, string $groupId): array
    {
        try {
            $token = $this->getSessionToken($sessionId);

            if (! $token) {
                throw new \Exception("No valid token found for session {$sessionId}");
            }

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$token}",
            ])->get("{$this->baseUrl}/api/v1/sessions/{$sessionId}/groups/{$groupId}/participants");

            if ($response->successful()) {
                $data = $response->json();

                Log::info('WhatsApp group participants retrieved', [
                    'session_id' => $sessionId,
                    'group_id' => $groupId,
                    'participants_count' => $data['count'] ?? 0,
                ]);

                return $data;
            }

            throw new \Exception("HTTP {$response->status()}: " . $response->body());
        } catch (\Exception $e) {
            Log::error('Failed to get WhatsApp group participants', [
                'session_id' => $sessionId,
                'group_id' => $groupId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function generateGroupInviteLink(string $sessionId, string $groupId): array
    {
        try {
            $token = $this->getSessionToken($sessionId);

            if (! $token) {
                throw new \Exception("No valid token found for session {$sessionId}");
            }

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$token}",
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/api/v1/sessions/{$sessionId}/groups/{$groupId}/invite");

            if ($response->successful()) {
                $data = $response->json();

                Log::info('WhatsApp group invite link generated', [
                    'session_id' => $sessionId,
                    'group_id' => $groupId,
                    'invite_code' => $data['inviteCode'] ?? null,
                ]);

                return $data;
            }

            throw new \Exception("HTTP {$response->status()}: " . $response->body());
        } catch (\Exception $e) {
            Log::error('Failed to generate WhatsApp group invite link', [
                'session_id' => $sessionId,
                'group_id' => $groupId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function getGroupMetadata(string $sessionId, string $groupId): array
    {
        try {
            $token = $this->getSessionToken($sessionId);

            if (! $token) {
                throw new \Exception("No valid token found for session {$sessionId}");
            }

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$token}",
            ])->get("{$this->baseUrl}/api/v1/sessions/{$sessionId}/groups/{$groupId}/metadata");

            if ($response->successful()) {
                $data = $response->json();

                Log::info('WhatsApp group metadata retrieved', [
                    'session_id' => $sessionId,
                    'group_id' => $groupId,
                ]);

                return $data['metadata'] ?? $data;
            }

            throw new \Exception("HTTP {$response->status()}: " . $response->body());
        } catch (\Exception $e) {
            Log::error('Failed to get WhatsApp group metadata', [
                'session_id' => $sessionId,
                'group_id' => $groupId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function createGroup(string $sessionId, string $name, string $description = '', array $participants = []): array
    {
        try {
            $token = $this->getSessionToken($sessionId);

            if (! $token) {
                throw new \Exception("No valid token found for session {$sessionId}");
            }

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$token}",
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/api/v1/sessions/{$sessionId}/groups", [
                'subject' => $name,
                'participants' => $participants,
                'description' => $description,
            ]);

            if ($response->successful()) {
                $data = $response->json();

                Log::info('WhatsApp group created', [
                    'session_id' => $sessionId,
                    'group_name' => $name,
                    'group_id' => $data['group']['id'] ?? null,
                ]);

                return $data;
            }

            throw new \Exception("HTTP {$response->status()}: " . $response->body());
        } catch (\Exception $e) {
            Log::error('Failed to create WhatsApp group', [
                'session_id' => $sessionId,
                'group_name' => $name,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function addParticipantsToGroup(string $sessionId, string $groupId, array $phoneNumbers): array
    {
        try {
            $token = $this->getSessionToken($sessionId);

            if (! $token) {
                throw new \Exception("No valid token found for session {$sessionId}");
            }

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$token}",
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/api/v1/sessions/{$sessionId}/groups/{$groupId}/participants", [
                'participants' => $phoneNumbers,
            ]);

            if ($response->successful()) {
                $data = $response->json();

                Log::info('Participants added to WhatsApp group', [
                    'session_id' => $sessionId,
                    'group_id' => $groupId,
                    'participants_count' => count($phoneNumbers),
                    'added_count' => count($data['results'] ?? []),
                ]);

                return $data;
            }

            throw new \Exception("HTTP {$response->status()}: " . $response->body());
        } catch (\Exception $e) {
            Log::error('Failed to add participants to WhatsApp group', [
                'session_id' => $sessionId,
                'group_id' => $groupId,
                'participants_count' => count($phoneNumbers),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function promoteGroupParticipants(string $sessionId, string $groupId, array $phoneNumbers): array
    {
        try {
            $token = $this->getSessionToken($sessionId);

            if (! $token) {
                throw new \Exception("No valid token found for session {$sessionId}");
            }

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$token}",
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/api/v1/sessions/{$sessionId}/groups/{$groupId}/participants/promote", [
                'participants' => $phoneNumbers,
            ]);

            if ($response->successful()) {
                $data = $response->json();

                Log::info('Participants promoted in WhatsApp group', [
                    'session_id' => $sessionId,
                    'group_id' => $groupId,
                    'participants_count' => count($phoneNumbers),
                ]);

                return $data;
            }

            throw new \Exception("HTTP {$response->status()}: " . $response->body());
        } catch (\Exception $e) {
            Log::error('Failed to promote participants in WhatsApp group', [
                'session_id' => $sessionId,
                'group_id' => $groupId,
                'participants_count' => count($phoneNumbers),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}