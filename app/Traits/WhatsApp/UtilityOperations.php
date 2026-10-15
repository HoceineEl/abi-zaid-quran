<?php

namespace App\Traits\WhatsApp;

use App\Models\WhatsAppSession;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
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
            return $this->results($this->gowa($instanceName)->get('/user/check', ['phone' => $number]));
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

        // Layer 3: WhatsApp API (slow, last resort)
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
     * Fetch joined groups directly from the WhatsApp API, returning only id and subject.
     */
    protected function fetchSessionGroupsFromApi(string $instanceName): array
    {
        return collect($this->results($this->gowa($instanceName, 30)->get('/user/my/groups'))['data'] ?? [])
            ->map(fn (array $group) => [
                'id' => $group['JID'] ?? '',
                'subject' => $group['Name'] ?? '',
            ])
            ->filter(fn (array $group) => $group['id'] !== '')
            ->values()
            ->all();
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
            $data = $this->results($this->gowa($instanceName)->get('/group/participants', ['group_id' => $groupJid]));

            Log::info('WhatsApp group participants retrieved', [
                'instance' => $instanceName,
                'group_jid' => $groupJid,
            ]);

            return $data;
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
            $data = $this->results($this->gowa($instanceName)->get('/group/invite-link', ['group_id' => $groupJid]));

            Log::info('WhatsApp group invite link generated', [
                'instance' => $instanceName,
                'group_jid' => $groupJid,
                'invite_link' => $data['invite_link'] ?? null,
            ]);

            return $data;
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
            $data = $this->results($this->gowa($instanceName, 30)->post('/group', [
                'title' => $name,
                'participants' => $participants,
            ]));

            $groupJid = $data['group_id'] ?? null;

            if ($groupJid && $description !== '') {
                $this->gowa($instanceName)->post('/group/topic', [
                    'group_id' => $groupJid,
                    'topic' => $description,
                ]);
            }

            Log::info('WhatsApp group created', [
                'instance' => $instanceName,
                'group_name' => $name,
                'group_jid' => $groupJid,
            ]);

            return ['id' => $groupJid, ...$data];
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
            $records = $this->fetchChatMessages(
                $instanceName,
                $groupJid,
                $dateFrom ? Carbon::parse($dateFrom)->startOfDay() : null,
                $dateTo ? Carbon::parse($dateTo)->endOfDay() : null,
                $limit,
            );

            Log::info('WhatsApp group messages retrieved', [
                'instance' => $instanceName,
                'group_jid' => $groupJid,
                'messages_count' => count($records),
            ]);

            return $records;
        } catch (\Exception $e) {
            Log::error('Failed to find WhatsApp group messages', [
                'instance' => $instanceName,
                'group_jid' => $groupJid,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    protected function fetchChatMessages(string $instanceName, string $chatJid, ?Carbon $from = null, ?Carbon $to = null, int $limit = 1000): array
    {
        $records = [];
        $pageSize = 100;

        do {
            $page = $this->results($this->gowa($instanceName, 30)->get('/chat/'.rawurlencode($chatJid).'/messages', array_filter([
                'limit' => $pageSize,
                'offset' => count($records),
                'start_time' => $from?->toIso8601String(),
                'end_time' => $to?->toIso8601String(),
            ])));

            $batch = $page['data'] ?? [];
            $records = [...$records, ...$batch];
            $total = $page['pagination']['total'] ?? 0;
        } while (count($batch) === $pageSize && count($records) < min($total, $limit));

        return array_slice($records, 0, $limit);
    }

    public function getGroupAttendeesForDate(string $instanceName, string $groupJid, string $date, array $allowedMessageTypes = ['audio']): array
    {
        $parsedDate = Carbon::parse($date);

        $lidToPhone = $this->resolveLidToPhoneMap($instanceName, $groupJid);
        $ownerPhone = $this->resolveInstanceOwnerPhone($instanceName);

        try {
            $messages = $this->fetchChatMessages(
                $instanceName,
                $groupJid,
                $parsedDate->copy()->startOfDay(),
                $parsedDate->copy()->endOfDay(),
            );
        } catch (\Exception $e) {
            Log::error('Failed to find WhatsApp group messages', [
                'instance' => $instanceName,
                'group_jid' => $groupJid,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        $senderPhones = collect($messages)
            ->filter(function (array $message) use ($groupJid, $allowedMessageTypes): bool {
                if (($message['chat_jid'] ?? '') !== $groupJid) {
                    return false;
                }

                $mediaType = $message['media_type'] ?? null;
                $messageType = blank($mediaType) ? 'text' : $mediaType;

                if (! in_array($messageType, $allowedMessageTypes, true)) {
                    return false;
                }

                // Text: only count messages that contain a completion/submission keyword.
                // This excludes admin curriculum announcements, reactions, mentions, and noise.
                if ($messageType === 'text') {
                    return $this->isSubmissionText($message['content'] ?? '');
                }

                return true;
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
        if ($message['is_from_me'] ?? false) {
            return $ownerPhone;
        }

        $sender = $message['sender_jid'] ?? '';

        if (str_contains($sender, '@s.whatsapp.net')) {
            return $this->stripWhatsAppSuffix($sender);
        }

        return $lidToPhone[$this->stripLidSuffix($sender)] ?? null;
    }

    protected function resolveInstanceOwnerPhone(string $instanceName): ?string
    {
        try {
            $ownerJid = $this->getInstanceStatus($instanceName)['instance']['ownerJid'] ?? null;
        } catch (\Exception) {
            return null;
        }

        return $ownerJid ? $this->stripWhatsAppSuffix($ownerJid) : null;
    }

    /**
     * Resolve LID-to-phone map from the participants API, using cache as safety net.
     */
    protected function resolveLidToPhoneMap(string $instanceName, string $groupJid): array
    {
        $cacheKey = "whatsapp_lid_map_{$groupJid}";

        try {
            $map = $this->parseLidToPhoneMap($this->getGroupParticipants($instanceName, $groupJid));

            if (! empty($map)) {
                Cache::put($cacheKey, $map, now()->addHours(6));

                return $map;
            }
        } catch (\Exception $e) {
            Log::warning('Participants fetch failed, using cache', [
                'instance' => $instanceName,
                'group_jid' => $groupJid,
                'error' => $e->getMessage(),
            ]);
        }

        return Cache::get($cacheKey, []);
    }

    /**
     * Parse the participants API result into a LID -> phone number map.
     */
    protected function parseLidToPhoneMap(array $data): array
    {
        $map = [];

        foreach ($data['participants'] ?? [] as $participant) {
            $phone = $this->stripWhatsAppSuffix($participant['phone_number'] ?? '');

            foreach ([$participant['lid'] ?? null, $participant['jid'] ?? null] as $id) {
                if ($phone && $id && str_ends_with($id, '@lid')) {
                    $map[$this->stripLidSuffix($id)] = $phone;
                }
            }
        }

        return $map;
    }

    protected function stripWhatsAppSuffix(string $jid): string
    {
        return preg_replace('/(:\d+)?@s\.whatsapp\.net$/', '', $jid);
    }

    protected function stripLidSuffix(string $jid): string
    {
        return preg_replace('/(:\d+)?@lid$/', '', $jid);
    }

    /**
     * Returns true if the message text looks like a student submission (completion report).
     *
     * Filters out: admin curriculum announcements, short noise, @mentions, reactions.
     * Confirmed patterns from real group data:
     *   تم / تمت / أتممت        → "تم بحمد الله مراجعة الحزب 1"
     *   قرأت / قراءة             → "قرأت إلى ص 59"
     *   راجعت / مراجعة          → "تمت مراجعة الحزبين 1و2"
     *   حفظت / استظهرت          → audio groups that also accept text
     *   بحمد الله / بفضل الله   → "تم بفضل الله مراجعة الحزبين 4و3"
     *   ولله الحمد / الحمد لله  → "تم ولله الحمد مراجعة الحزب 59"
     *   أنهيت / انتهيت / اتممت  → variant completion phrases
     */
    protected function isSubmissionText(string $text): bool
    {
        if (blank($text)) {
            return false;
        }

        $text = mb_strtolower(trim($text));

        $keywords = [
            'تم', 'تمت', 'اتممت', 'أتممت', 'أنهيت', 'انتهيت', 'انتهى',
            'قرأت', 'قراءة', 'قرأ',
            'راجعت', 'مراجعة',
            'حفظت', 'حفظ', 'استظهرت', 'استظهر',
            'بحمد الله', 'بفضل الله', 'ولله الحمد', 'الحمد لله',
        ];

        foreach ($keywords as $keyword) {
            if (str_contains($text, $keyword)) {
                return true;
            }
        }

        return false;
    }

    public function addParticipantsToGroup(string $instanceName, string $groupJid, array $phoneNumbers): array
    {
        try {
            $data = $this->results($this->gowa($instanceName, 30)->post('/group/participants', [
                'group_id' => $groupJid,
                'participants' => $phoneNumbers,
            ]));

            Log::info('Participants added to WhatsApp group', [
                'instance' => $instanceName,
                'group_jid' => $groupJid,
                'participants_count' => count($phoneNumbers),
            ]);

            return $data;
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
            $data = $this->results($this->gowa($instanceName, 30)->post('/group/participants/promote', [
                'group_id' => $groupJid,
                'participants' => $phoneNumbers,
            ]));

            Log::info('Participants promoted in WhatsApp group', [
                'instance' => $instanceName,
                'group_jid' => $groupJid,
                'participants_count' => count($phoneNumbers),
            ]);

            return $data;
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
