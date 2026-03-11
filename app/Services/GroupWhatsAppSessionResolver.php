<?php

namespace App\Services;

use App\Models\Group;
use App\Models\User;
use App\Models\WhatsAppSession;

class GroupWhatsAppSessionResolver
{
    /**
     * Fallback admin is the lowest-id user with role "admin".
     */
    public function resolveForGroup(Group $group): ?WhatsAppSession
    {
        $preferredUserId = $group->whatsapp_manager_id;

        if ($preferredUserId) {
            $preferredSession = WhatsAppSession::getUserActiveSession($preferredUserId);

            if ($preferredSession?->isConnected()) {
                return $preferredSession;
            }
        }

        $fallbackAdmin = $this->fallbackAdmin();

        if (! $fallbackAdmin) {
            return null;
        }

        $fallbackSession = WhatsAppSession::getUserActiveSession($fallbackAdmin->id);

        return $fallbackSession?->isConnected() ? $fallbackSession : null;
    }

    public function hasAvailableSession(Group $group): bool
    {
        return $this->resolveForGroup($group)?->isConnected() ?? false;
    }

    public function fallbackAdmin(): ?User
    {
        return User::query()
            ->where('role', 'admin')
            ->orderBy('id')
            ->first();
    }
}
