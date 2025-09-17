<?php

namespace App\Models;

use App\Enums\WhatsAppConnectionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class WhatsAppSession extends Model
{
    protected $table = 'whatsapp_sessions';

    protected $fillable = [
        'user_id',
        'name',
        'status',
        'qr_code',
        'session_data',
        'connected_at',
        'last_activity_at',
    ];

    protected $casts = [
        'status' => WhatsAppConnectionStatus::class,
        'connected_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'session_data' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(WhatsAppMessageHistory::class, 'session_id');
    }

    public function isConnected(): bool
    {
        return $this->status === WhatsAppConnectionStatus::CONNECTED;
    }

    public function markAsConnected(?array $sessionData = null): void
    {
        $updateData = [
            'status' => WhatsAppConnectionStatus::CONNECTED,
            'connected_at' => now(),
            'last_activity_at' => now(),
        ];

        if ($sessionData !== null) {
            $updateData['session_data'] = $sessionData;

            if (isset($sessionData['token']) && ! empty($sessionData['token'])) {
                Cache::put("whatsapp_token_{$this->id}", $sessionData['token'], now()->addHours(24));
            }
        }

        $this->update($updateData);
    }

    public function markAsDisconnected(): void
    {
        Cache::forget("whatsapp_token_{$this->id}");

        $this->update([
            'status' => WhatsAppConnectionStatus::DISCONNECTED,
            'connected_at' => null,
            'qr_code' => null,
        ]);
    }

    public function updateQrCode(?string $qrCode): void
    {
        // Let the service handle QR code cleaning
        $service = app(\App\Services\WhatsAppService::class);
        $cleanedQrCode = $service->cleanQrCodeData($qrCode);

        $this->update([
            'qr_code' => $cleanedQrCode,
            'last_activity_at' => now(),
        ]);
    }

    public function getQrCodeData(): ?string
    {
        return $this->qr_code;
    }

    public function getSessionToken(): ?string
    {
        return $this->session_data['token'] ?? null;
    }

    public function scopeActive($query)
    {
        return $query->where('status', '!=', WhatsAppConnectionStatus::DISCONNECTED);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public static function getUserSession(int $userId): ?self
    {
        return static::query()
            ->forUser($userId)
            ->first();
    }

    public static function getUserActiveSession(int $userId): ?self
    {
        return static::query()
            ->forUser($userId)
            ->active()
            ->first();
    }

    /**
     * Delete session with proper cleanup
     */
    public function delete()
    {
        try {
            DB::transaction(function () {
                // Clear cached data
                $this->clearCachedTokens();

                // Try to logout from service
                $this->tryLogoutFromService();

                // Delete the session (cascade will handle message histories)
                parent::delete();
            });

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to delete WhatsApp session', [
                'session_id' => $this->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Force delete session with cleanup (same as delete now due to cascade)
     */
    public function forceDeleteWithRelated(): bool
    {
        return $this->delete();
    }

    /**
     * Try to logout from WhatsApp service
     */
    protected function tryLogoutFromService(): void
    {
        try {
            if ($this->isConnected()) {
                $whatsappService = app(\App\Services\WhatsAppService::class);
                $whatsappService->logout($this);
            }
        } catch (\Exception $e) {
            \Log::warning('Failed to logout from WhatsApp service during deletion', [
                'session_id' => $this->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Clear all cached tokens and data for this session
     */
    protected function clearCachedTokens(): void
    {
        Cache::forget("whatsapp_token_{$this->id}");
        Cache::forget("last_poll_{$this->id}");
        Cache::forget("poll_count_{$this->id}");
        Cache::forget("error_count_{$this->id}");
    }

    /**
     * Boot method to handle model events
     */
    protected static function boot()
    {
        parent::boot();

        // When a session is being deleted, clean up related data
        static::deleting(function ($session) {
            $session->clearCachedTokens();
            $session->tryLogoutFromService();
        });
    }
}
