<?php

namespace App\Traits\WhatsApp;

use App\Models\WhatsAppSession;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

trait UtilityOperations
{
    public function refreshQrCode(WhatsAppSession $session): array
    {
        try {
            $instanceName = $session->name;
            $result = $this->connectInstance($instanceName);
            $base64Qr = $result['base64'] ?? null;

            Log::info('Refresh QR: Got connect result', [
                'instance' => $instanceName,
                'has_qr' => ! empty($base64Qr),
            ]);

            if (empty($base64Qr)) {
                $state = $this->getInstanceStatus($instanceName);
                $currentState = $state['instance']['state'] ?? 'close';

                if ($currentState === 'open') {
                    Log::info('Instance is already connected, no QR needed', ['instance' => $instanceName]);

                    return $state;
                }

                throw new \RuntimeException("No QR code available for instance {$instanceName}. State: {$currentState}");
            }

            $qrCode = $this->cleanQrCodeData($base64Qr);

            $session->update([
                'qr_code' => $qrCode,
                'session_data' => $result,
                'last_activity_at' => now(),
            ]);

            Log::info('WhatsApp QR code refreshed successfully', [
                'instance' => $instanceName,
                'has_qr_code' => ! empty($qrCode),
            ]);

            return $result;
        } catch (\Exception $e) {
            Log::error('Failed to refresh WhatsApp QR code', [
                'instance' => $session->name,
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

        if (str_starts_with($qrData, 'data:image/')) {
            return $qrData;
        }

        if (preg_match('/^[A-Za-z0-9+\/=]+$/', substr($qrData, 0, 100))) {
            return 'data:image/png;base64,'.$qrData;
        }

        return null;
    }

    public function checkNumber(string $instanceName, string $number): array
    {
        try {
            $response = Http::withHeaders($this->evolutionHeaders())
                ->post("{$this->baseUrl}/chat/whatsappNumbers/{$instanceName}", [
                    'numbers' => [$number],
                ]);

            if ($response->successful()) {
                return $response->json();
            }

            throw new \RuntimeException("HTTP {$response->status()}: ".$response->body());
        } catch (\Exception $e) {
            Log::error('Failed to check WhatsApp number', [
                'instance' => $instanceName,
                'number' => $number,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function getSessionGroups(string $instanceName): array
    {
        try {
            $response = Http::withHeaders($this->evolutionHeaders())
                ->get("{$this->baseUrl}/group/fetchAllGroups/{$instanceName}");

            if ($response->successful()) {
                $groups = $response->json();

                Log::info('WhatsApp groups retrieved', [
                    'instance' => $instanceName,
                    'groups_count' => count($groups),
                ]);

                return $groups;
            }

            throw new \RuntimeException("HTTP {$response->status()}: ".$response->body());
        } catch (\Exception $e) {
            Log::error('Failed to get WhatsApp groups', [
                'instance' => $instanceName,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function getGroupParticipants(string $instanceName, string $groupJid): array
    {
        try {
            $response = Http::withHeaders($this->evolutionHeaders())
                ->get("{$this->baseUrl}/group/participants/{$instanceName}", [
                    'groupJid' => $groupJid,
                ]);

            if ($response->successful()) {
                $data = $response->json();

                Log::info('WhatsApp group participants retrieved', [
                    'instance' => $instanceName,
                    'group_jid' => $groupJid,
                ]);

                return $data;
            }

            throw new \RuntimeException("HTTP {$response->status()}: ".$response->body());
        } catch (\Exception $e) {
            Log::error('Failed to get WhatsApp group participants', [
                'instance' => $instanceName,
                'group_jid' => $groupJid,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function generateGroupInviteLink(string $instanceName, string $groupJid): array
    {
        try {
            $response = Http::withHeaders($this->evolutionHeaders())
                ->get("{$this->baseUrl}/group/inviteCode/{$instanceName}", [
                    'groupJid' => $groupJid,
                ]);

            if ($response->successful()) {
                $data = $response->json();

                Log::info('WhatsApp group invite link generated', [
                    'instance' => $instanceName,
                    'group_jid' => $groupJid,
                    'invite_code' => $data['inviteCode'] ?? null,
                ]);

                return $data;
            }

            throw new \RuntimeException("HTTP {$response->status()}: ".$response->body());
        } catch (\Exception $e) {
            Log::error('Failed to generate WhatsApp group invite link', [
                'instance' => $instanceName,
                'group_jid' => $groupJid,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function createGroup(string $instanceName, string $name, string $description = '', array $participants = []): array
    {
        try {
            $response = Http::withHeaders($this->evolutionHeaders())
                ->post("{$this->baseUrl}/group/create/{$instanceName}", [
                    'subject' => $name,
                    'description' => $description,
                    'participants' => $participants,
                ]);

            if ($response->successful()) {
                $data = $response->json();

                Log::info('WhatsApp group created', [
                    'instance' => $instanceName,
                    'group_name' => $name,
                    'group_jid' => $data['id'] ?? null,
                ]);

                return $data;
            }

            throw new \RuntimeException("HTTP {$response->status()}: ".$response->body());
        } catch (\Exception $e) {
            Log::error('Failed to create WhatsApp group', [
                'instance' => $instanceName,
                'group_name' => $name,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function addParticipantsToGroup(string $instanceName, string $groupJid, array $phoneNumbers): array
    {
        try {
            $response = Http::withHeaders($this->evolutionHeaders())
                ->post("{$this->baseUrl}/group/updateParticipant/{$instanceName}", [
                    'groupJid' => $groupJid,
                    'action' => 'add',
                    'participants' => $phoneNumbers,
                ]);

            if ($response->successful()) {
                $data = $response->json();

                Log::info('Participants added to WhatsApp group', [
                    'instance' => $instanceName,
                    'group_jid' => $groupJid,
                    'participants_count' => count($phoneNumbers),
                ]);

                return $data;
            }

            throw new \RuntimeException("HTTP {$response->status()}: ".$response->body());
        } catch (\Exception $e) {
            Log::error('Failed to add participants to WhatsApp group', [
                'instance' => $instanceName,
                'group_jid' => $groupJid,
                'participants_count' => count($phoneNumbers),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function promoteGroupParticipants(string $instanceName, string $groupJid, array $phoneNumbers): array
    {
        try {
            $response = Http::withHeaders($this->evolutionHeaders())
                ->post("{$this->baseUrl}/group/updateParticipant/{$instanceName}", [
                    'groupJid' => $groupJid,
                    'action' => 'promote',
                    'participants' => $phoneNumbers,
                ]);

            if ($response->successful()) {
                $data = $response->json();

                Log::info('Participants promoted in WhatsApp group', [
                    'instance' => $instanceName,
                    'group_jid' => $groupJid,
                    'participants_count' => count($phoneNumbers),
                ]);

                return $data;
            }

            throw new \RuntimeException("HTTP {$response->status()}: ".$response->body());
        } catch (\Exception $e) {
            Log::error('Failed to promote participants in WhatsApp group', [
                'instance' => $instanceName,
                'group_jid' => $groupJid,
                'participants_count' => count($phoneNumbers),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
