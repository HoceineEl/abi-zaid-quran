<?php

namespace App\Traits\WhatsApp;

use App\Models\WhatsAppSession;
use Carbon\Carbon;
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
                ->get("{$this->baseUrl}/group/fetchAllGroups/{$instanceName}", [
                    'getParticipants' => 'false',
                ]);

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

    public function findGroupMessages(string $instanceName, string $groupJid, ?string $dateFrom = null, ?string $dateTo = null, int $limit = 200): array
    {
        try {
            $where = ['remoteJid' => $groupJid];

            if ($dateFrom || $dateTo) {
                $where['messageTimestamp'] = array_filter([
                    'gte' => $dateFrom ? Carbon::parse($dateFrom)->startOfDay()->toISOString() : null,
                    'lte' => $dateTo ? Carbon::parse($dateTo)->endOfDay()->toISOString() : null,
                ]);
            }

            $response = Http::withHeaders($this->evolutionHeaders())
                ->post("{$this->baseUrl}/chat/findMessages/{$instanceName}", [
                    'where' => $where,
                    'page' => 1,
                    'offset' => $limit,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $records = $data['messages']['records'] ?? $data['records'] ?? $data;

                if (! is_array($records)) {
                    $records = [];
                }

                Log::info('WhatsApp group messages retrieved', [
                    'instance' => $instanceName,
                    'group_jid' => $groupJid,
                    'messages_count' => count($records),
                ]);

                return $records;
            }

            throw new \RuntimeException("HTTP {$response->status()}: ".$response->body());
        } catch (\Exception $e) {
            Log::error('Failed to find WhatsApp group messages', [
                'instance' => $instanceName,
                'group_jid' => $groupJid,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function getGroupAttendeesForDate(string $instanceName, string $groupJid, string $date, array $allowedMessageTypes = ['audioMessage']): array
    {
        $lidToPhone = $this->buildLidToPhoneMap($instanceName, $groupJid);
        $messages = $this->findGroupMessages($instanceName, $groupJid, $date, $date);

        $senderPhones = collect($messages)
            ->filter(function (array $message) use ($groupJid, $allowedMessageTypes): bool {
                $key = $message['key'] ?? [];

                return ($key['remoteJid'] ?? '') === $groupJid
                    && ! ($key['fromMe'] ?? false)
                    && in_array($message['messageType'] ?? '', $allowedMessageTypes, true);
            })
            ->map(fn (array $message) => $this->resolveMessageSenderPhone($message, $lidToPhone))
            ->filter()
            ->unique()
            ->values()
            ->all();

        Log::info('WhatsApp group attendees extracted for date', [
            'instance' => $instanceName,
            'group_jid' => $groupJid,
            'date' => $date,
            'attendees_count' => count($senderPhones),
        ]);

        return $senderPhones;
    }

    /**
     * Resolve the sender phone number from a WhatsApp group message.
     */
    protected function resolveMessageSenderPhone(array $message, array $lidToPhone): ?string
    {
        $key = $message['key'] ?? [];

        // participantAlt has the @s.whatsapp.net format (most reliable)
        $participantAlt = $key['participantAlt'] ?? null;
        if ($participantAlt && str_contains($participantAlt, '@s.whatsapp.net')) {
            return $this->stripWhatsAppSuffix($participantAlt);
        }

        // participant may have @s.whatsapp.net or @lid
        $participant = $key['participant'] ?? null;
        if ($participant) {
            if (str_contains($participant, '@s.whatsapp.net')) {
                return $this->stripWhatsAppSuffix($participant);
            }

            $lid = str_replace('@lid', '', $participant);

            return $lidToPhone[$lid] ?? null;
        }

        // Fallback: pushName may contain LID in some Evolution API versions
        $pushName = $message['pushName'] ?? null;

        return ($pushName && isset($lidToPhone[$pushName])) ? $lidToPhone[$pushName] : null;
    }

    protected function buildLidToPhoneMap(string $instanceName, string $groupJid): array
    {
        $participants = $this->getGroupParticipants($instanceName, $groupJid);
        $participantsList = $participants['participants'] ?? $participants;

        $map = [];
        foreach ($participantsList as $p) {
            $lid = str_replace('@lid', '', $p['id'] ?? '');
            $phone = $this->stripWhatsAppSuffix($p['phoneNumber'] ?? '');

            if ($lid && $phone) {
                $map[$lid] = $phone;
            }
        }

        return $map;
    }

    protected function stripWhatsAppSuffix(string $jid): string
    {
        return str_replace('@s.whatsapp.net', '', $jid);
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
