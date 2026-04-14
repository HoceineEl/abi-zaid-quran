<?php

namespace App\Models;

use Exception;
use App\Enums\WhatsAppConnectionStatus;
use App\Services\WhatsAppService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WhatsAppSession extends Model
{
    protected $table = 'whatsapp_sessions';

    protected $fillable = [
        'user_id',
        'name',
        'status',
        'qr_code',
        'session_data',
        'cached_groups',
        'connected_at',
        'last_activity_at',
        'payment_reminder_template',
    ];

    protected $casts = [
        'status' => WhatsAppConnectionStatus::class,
        'connected_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'session_data' => 'array',
        'cached_groups' => 'array',
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
        }

        $this->update($updateData);
    }

    public function markAsDisconnected(): void
    {
        $this->update([
            'status' => WhatsAppConnectionStatus::DISCONNECTED,
            'connected_at' => null,
            'qr_code' => null,
        ]);
    }

    public function updateQrCode(?string $qrCode): void
    {
        $cleanedQrCode = app(WhatsAppService::class)->cleanQrCodeData($qrCode);

        $this->update([
            'qr_code' => $cleanedQrCode,
            'last_activity_at' => now(),
        ]);
    }

    public function getQrCodeData(): ?string
    {
        return $this->qr_code;
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

    protected function tryLogoutFromService(): void
    {
        try {
            if ($this->isConnected()) {
                app(WhatsAppService::class)->logout($this);
            }
        } catch (Exception $e) {
            Log::warning('Failed to logout from WhatsApp service during deletion', [
                'session_id' => $this->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function cacheGroups(array $groups): void
    {
        $this->update(['cached_groups' => $groups]);
    }

    public function clearRelatedCaches(): void
    {
        Cache::forget("whatsapp_groups_{$this->name}");
        $this->updateQuietly(['cached_groups' => null]);
    }

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($session) {
            $session->tryLogoutFromService();
            $session->clearRelatedCaches();
        });
    }
}
