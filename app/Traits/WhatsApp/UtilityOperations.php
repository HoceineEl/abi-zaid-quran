<?php

namespace App\Traits\WhatsApp;

use App\Models\WhatsAppSession;
use Carbon\Carbon;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

trait UtilityOperations
{
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
                ->timeout(15)
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

    /**
     * Get session groups (cached). Returns only id and subject for each group.
     *
     * Non-empty results cached forever; empty/failed results are not cached.
     * Use clearSessionGroupsCache() (refresh button) to force a fresh fetch.
     */
    public function getSessionGroups(string $instanceName): array
    {
        $cacheKey = $this->groupsCacheKey($instanceName);

        // Layer 1: Laravel cache (fastest)
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        // Layer 2: DB persistent storage (survives server restart)
        $session = WhatsAppSession::where('name', $instanceName)->first();
        if ($session?->cached_groups) {
            Cache::forever($cacheKey, $session->cached_groups);

            return $session->cached_groups;
        }

        // Layer 3: Evolution API (slow, last resort)
        try {
            $groups = $this->fetchSessionGroupsFromApi($instanceName);
        } catch (\Exception $e) {
            Log::error('Failed to get WhatsApp groups', [
                'instance' => $instanceName,
                'error' => $e->getMessage(),
            ]);
            $groups = [];
        }

        if (! empty($groups)) {
            Cache::forever($cacheKey, $groups);
            $session?->cacheGroups($groups);
        }

        return $groups;
    }

    /**
     * Fetch groups directly from the Evolution API, returning only id and subject.
     * Falls back to findChats endpoint if fetchAllGroups times out.
     */
    protected function fetchSessionGroupsFromApi(string $instanceName): array
    {
        // Primary: fetchAllGroups (returns full group info)
        try {
            $response = Http::withHeaders($this->evolutionHeaders())
                ->timeout(15)
                ->get("{$this->baseUrl}/group/fetchAllGroups/{$instanceName}", [
                    'getParticipants' => 'false',
                ]);

            if ($response->successful() && is_array($response->json())) {
                return collect($response->json())
                    ->map(fn (array $group) => [
                        'id' => $group['id'] ?? '',
                        'subject' => $group['subject'] ?? $group['name'] ?? '',
                    ])
                    ->filter(fn (array $group) => $group['id'] !== '')
                    ->values()
                    ->all();
            }
        } catch (\Exception $e) {
            Log::warning('fetchAllGroups failed, trying findChats fallback', [
                'instance' => $instanceName,
                'error' => $e->getMessage(),
            ]);
        }

        // Fallback: findChats (lighter, extracts groups from chat list)
        try {
            $response = Http::withHeaders($this->evolutionHeaders())
                ->timeout(15)
                ->post("{$this->baseUrl}/chat/findChats/{$instanceName}");

            if ($response->successful() && is_array($response->json())) {
                return collect($response->json())
                    ->filter(fn (array $chat) => str_ends_with($chat['remoteJid'] ?? '', '@g.us'))
                    ->map(fn (array $chat) => [
                        'id' => $chat['remoteJid'],
                        'subject' => $chat['pushName'] ?? $chat['remoteJid'],
                    ])
                    ->values()
                    ->all();
            }
        } catch (\Exception $e) {
            Log::warning('findChats fallback also failed', [
                'instance' => $instanceName,
                'error' => $e->getMessage(),
            ]);
        }

        return [];
    }

    public function clearSessionGroupsCache(string $instanceName): void
    {
        Cache::forget($this->groupsCacheKey($instanceName));
        WhatsAppSession::where('name', $instanceName)
            ->update(['cached_groups' => null]);
    }

    protected function groupsCacheKey(string $instanceName): string
    {
        return "whatsapp_groups_{$instanceName}";
    }

    public function getGroupParticipants(string $instanceName, string $groupJid): array
    {
        try {
            $response = Http::withHeaders($this->evolutionHeaders())
                ->timeout(15)
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
                ->timeout(15)
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
                ->timeout(15)
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
            $where = [
                'key' => ['remoteJid' => $groupJid],
            ];

            if ($dateFrom && $dateTo) {
                $where['messageTimestamp'] = [
                    'gte' => Carbon::parse($dateFrom)->startOfDay()->toISOString(),
                    'lte' => Carbon::parse($dateTo)->endOfDay()->toISOString(),
                ];
            } elseif ($dateFrom) {
                // Both gte and lte are required — default lte to end of today
                $where['messageTimestamp'] = [
                    'gte' => Carbon::parse($dateFrom)->startOfDay()->toISOString(),
                    'lte' => now()->endOfDay()->toISOString(),
                ];
            } elseif ($dateTo) {
                // Both gte and lte are required — default gte to epoch
                $where['messageTimestamp'] = [
                    'gte' => Carbon::createFromTimestamp(0)->toISOString(),
                    'lte' => Carbon::parse($dateTo)->endOfDay()->toISOString(),
                ];
            }

            $response = Http::withHeaders($this->evolutionHeaders())
                ->timeout(30)
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
        $parsedDate = Carbon::parse($date);
        $where = [
            'key' => ['remoteJid' => $groupJid],
            'messageTimestamp' => [
                'gte' => $parsedDate->copy()->startOfDay()->toISOString(),
                'lte' => $parsedDate->copy()->endOfDay()->toISOString(),
            ],
        ];

        $responses = Http::pool(fn (Pool $pool) => [
            $pool->as('participants')
                ->withHeaders($this->evolutionHeaders())
                ->timeout(15)
                ->get("{$this->baseUrl}/group/participants/{$instanceName}", ['groupJid' => $groupJid]),
            $pool->as('messages')
                ->withHeaders($this->evolutionHeaders())
                ->timeout(15)
                ->post("{$this->baseUrl}/chat/findMessages/{$instanceName}", [
                    'where' => $where,
                    'page' => 1,
                    'offset' => 200,
                ]),
            $pool->as('instance')
                ->withHeaders($this->evolutionHeaders())
                ->timeout(10)
                ->get("{$this->baseUrl}/instance/connectionState/{$instanceName}"),
        ]);

        $participantsResponse = $responses['participants'] ?? null;
        $messagesResponse = $responses['messages'] ?? null;
        $instanceResponse = $responses['instance'] ?? null;

        $lidToPhone = $this->resolveLidToPhoneMap($participantsResponse, $instanceName, $groupJid);
        $ownerPhone = $this->resolveInstanceOwnerPhone($instanceResponse, $instanceName);

        $messages = ($messagesResponse instanceof Response)
            ? $this->parseMessageRecords($messagesResponse, $instanceName, $groupJid)
            : [];

        $senderPhones = collect($messages)
            ->filter(function (array $message) use ($groupJid, $allowedMessageTypes): bool {
                $key = $message['key'] ?? [];

                return ($key['remoteJid'] ?? '') === $groupJid
                    && in_array($message['messageType'] ?? '', $allowedMessageTypes, true);
            })
            ->map(fn (array $message) => $this->resolveMessageSenderPhone($message, $lidToPhone, $ownerPhone))
            ->filter()
            ->unique()
            ->values()
            ->all();

        Log::info('WhatsApp group attendees extracted for date', [
            'instance' => $instanceName,
            'group_jid' => $groupJid,
            'date' => $date,
            'attendees_count' => count($senderPhones),
            'lid_map_size' => count($lidToPhone),
            'owner_phone' => $ownerPhone,
        ]);

        return $senderPhones;
    }

    /**
     * Resolve the sender phone number from a WhatsApp group message.
     */
    protected function resolveMessageSenderPhone(array $message, array $lidToPhone, ?string $ownerPhone = null): ?string
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
            $phone = $lidToPhone[$lid] ?? null;
            if ($phone) {
                return $phone;
            }
        }

        // fromMe messages are from the instance owner
        if ($key['fromMe'] ?? false) {
            return $ownerPhone;
        }

        // Fallback: pushName may contain LID in some Evolution API versions
        $pushName = $message['pushName'] ?? null;

        return $lidToPhone[$pushName] ?? null;
    }

    /**
     * Extract the instance owner's phone from the connectionState response.
     */
    protected function resolveInstanceOwnerPhone(mixed $response, string $instanceName): ?string
    {
        if (! ($response instanceof Response) || ! $response->successful()) {
            // Try fetching instance info directly
            try {
                $response = Http::withHeaders($this->evolutionHeaders())
                    ->timeout(5)
                    ->get("{$this->baseUrl}/instance/connectionState/{$instanceName}");
            } catch (\Exception $e) {
                return null;
            }
        }

        $data = $response->json();
        $ownerJid = $data['instance']['ownerJid'] ?? $data['ownerJid'] ?? null;

        return $ownerJid ? $this->stripWhatsAppSuffix($ownerJid) : null;
    }

    /**
     * Resolve LID-to-phone map: try pool response, fallback to sequential request, use cache as safety net.
     */
    protected function resolveLidToPhoneMap(mixed $poolResponse, string $instanceName, string $groupJid): array
    {
        $cacheKey = "whatsapp_lid_map_{$groupJid}";

        // Try pool response first
        if ($poolResponse instanceof Response) {
            $map = $this->parseLidToPhoneMap($poolResponse, $instanceName, $groupJid);
            if (! empty($map)) {
                Cache::put($cacheKey, $map, now()->addHours(6));

                return $map;
            }
        }

        // Pool failed — try a separate sequential request
        try {
            $response = Http::withHeaders($this->evolutionHeaders())
                ->timeout(10)
                ->get("{$this->baseUrl}/group/participants/{$instanceName}", ['groupJid' => $groupJid]);

            $map = $this->parseLidToPhoneMap($response, $instanceName, $groupJid);
            if (! empty($map)) {
                Cache::put($cacheKey, $map, now()->addHours(6));

                return $map;
            }
        } catch (\Exception $e) {
            Log::warning('Sequential participants fetch failed, using cache', [
                'instance' => $instanceName,
                'group_jid' => $groupJid,
                'error' => $e->getMessage(),
            ]);
        }

        // Use cached map as last resort
        return Cache::get($cacheKey, []);
    }

    /**
     * Parse the participants API response into a LID -> phone number map.
     */
    protected function parseLidToPhoneMap(Response $response, string $instanceName, string $groupJid): array
    {
        if (! $response->successful()) {
            Log::error('Failed to get WhatsApp group participants', [
                'instance' => $instanceName,
                'group_jid' => $groupJid,
                'status' => $response->status(),
            ]);

            return [];
        }

        $data = $response->json();
        $participants = $data['participants'] ?? $data;
        $map = [];

        foreach ($participants as $p) {
            $lid = str_replace('@lid', '', $p['id'] ?? '');
            $phone = $this->stripWhatsAppSuffix($p['phoneNumber'] ?? '');

            if ($lid && $phone) {
                $map[$lid] = $phone;
            }
        }

        return $map;
    }

    /**
     * Parse the messages API response into an array of message records.
     */
    protected function parseMessageRecords(Response $response, string $instanceName, string $groupJid): array
    {
        if (! $response->successful()) {
            Log::error('Failed to find WhatsApp group messages', [
                'instance' => $instanceName,
                'group_jid' => $groupJid,
                'status' => $response->status(),
            ]);

            return [];
        }

        $data = $response->json();
        $records = $data['messages']['records'] ?? $data['records'] ?? $data;

        return is_array($records) ? $records : [];
    }

    protected function stripWhatsAppSuffix(string $jid): string
    {
        return str_replace('@s.whatsapp.net', '', $jid);
    }

    public function addParticipantsToGroup(string $instanceName, string $groupJid, array $phoneNumbers): array
    {
        try {
            $response = Http::withHeaders($this->evolutionHeaders())
                ->timeout(15)
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
                ->timeout(15)
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
