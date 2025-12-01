<?php

namespace App\Enums;

enum WhatsAppConnectionStatus: string
{
    case DISCONNECTED = 'disconnected';
    case CREATING = 'creating';
    case CONNECTING = 'connecting';
    case PENDING = 'pending';
    case GENERATING_QR = 'generating_qr';
    case CONNECTED = 'connected';

    public function getLabel(): string
    {
        return match ($this) {
            self::DISCONNECTED => 'غير متصل',
            self::CREATING => 'إنشاء الجلسة',
            self::CONNECTING => 'الاتصال',
            self::PENDING => 'في الانتظار',
            self::GENERATING_QR => 'إنشاء رمز QR',
            self::CONNECTED => 'متصل',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::DISCONNECTED => 'gray',
            self::CREATING, self::CONNECTING, self::GENERATING_QR => 'info',
            self::PENDING => 'warning',
            self::CONNECTED => 'success',
        };
    }

    public function isActive(): bool
    {
        return in_array($this, [
            self::CONNECTED,
            self::CREATING,
            self::CONNECTING,
            self::PENDING,
            self::GENERATING_QR,
        ]);
    }

    public function canShowQrCode(): bool
    {
        return in_array($this, [
            self::CREATING,
            self::CONNECTING,
            self::PENDING,
            self::GENERATING_QR,
        ]);
    }

    public static function fromApiStatus(string $apiStatus): self
    {
        return match (strtoupper($apiStatus)) {
            'CONNECTED' => self::CONNECTED,
            'GENERATING_QR' => self::GENERATING_QR,
            'CREATING' => self::CREATING,
            'CONNECTING' => self::CONNECTING,
            'PENDING' => self::PENDING,
            'DISCONNECTED' => self::DISCONNECTED,
            default => self::DISCONNECTED,
        };
    }

    public function canReloadQr(): bool
    {
        return in_array($this, [
            self::CREATING,
            self::CONNECTING,
            self::PENDING,
            self::GENERATING_QR,
        ]);
    }

    public function canStartSession(): bool
    {
        return $this === self::DISCONNECTED;
    }

    public function shouldPoll(): bool
    {
        return in_array($this, [
            self::CREATING,
            self::CONNECTING,
            self::PENDING,
            self::GENERATING_QR,
        ]);
    }

    public function shouldShowQrCode(): bool
    {
        return in_array($this, [
            self::CREATING,
            self::CONNECTING,
            self::PENDING,
            self::GENERATING_QR,
        ]);
    }

    public function canLogout(): bool
    {
        return in_array($this, [
            self::CONNECTED,
            self::CREATING,
            self::CONNECTING,
            self::PENDING,
            self::GENERATING_QR,
        ]);
    }

    /**
     * Check if transition to a new status is valid.
     * This prevents invalid status changes like going from DISCONNECTED to CONNECTED directly.
     */
    public function canTransitionTo(self $newStatus): bool
    {
        // Same status is always allowed
        if ($this === $newStatus) {
            return true;
        }

        $validTransitions = [
            self::DISCONNECTED->value => [
                self::CREATING,
                self::CONNECTING,
                self::PENDING,
                self::GENERATING_QR,
            ],
            self::CREATING->value => [
                self::GENERATING_QR,
                self::CONNECTING,
                self::PENDING,
                self::CONNECTED,
                self::DISCONNECTED,
            ],
            self::CONNECTING->value => [
                self::GENERATING_QR,
                self::PENDING,
                self::CONNECTED,
                self::DISCONNECTED,
            ],
            self::GENERATING_QR->value => [
                self::PENDING,
                self::CONNECTING,
                self::CONNECTED,
                self::DISCONNECTED,
            ],
            self::PENDING->value => [
                self::GENERATING_QR,
                self::CONNECTING,
                self::CONNECTED,
                self::DISCONNECTED,
            ],
            self::CONNECTED->value => [
                self::DISCONNECTED,
            ],
        ];

        return in_array($newStatus, $validTransitions[$this->value] ?? []);
    }

    /**
     * Get the polling interval in milliseconds for this status.
     */
    public function getPollingInterval(): int
    {
        return match ($this) {
            self::CREATING => 2000,       // Fast polling when creating
            self::CONNECTING => 2000,     // Fast polling when connecting
            self::GENERATING_QR => 3000,  // Slightly slower for QR generation
            self::PENDING => 5000,        // QR is shown, waiting for scan
            self::CONNECTED => 30000,     // Health check every 30 seconds
            self::DISCONNECTED => 0,      // No polling when disconnected
        };
    }
}
