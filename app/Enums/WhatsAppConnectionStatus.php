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
        return $this !== self::DISCONNECTED;
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
        return match (strtolower($apiStatus)) {
            'open', 'connected' => self::CONNECTED,
            'connecting' => self::CONNECTING,
            'close', 'closed', 'disconnected' => self::DISCONNECTED,
            'generating_qr' => self::GENERATING_QR,
            'creating' => self::CREATING,
            'pending' => self::PENDING,
            default => self::DISCONNECTED,
        };
    }

    public function canReloadQr(): bool
    {
        return $this->canShowQrCode();
    }

    public function canStartSession(): bool
    {
        return $this === self::DISCONNECTED;
    }

    public function shouldPoll(): bool
    {
        return $this->canShowQrCode();
    }

    public function shouldShowQrCode(): bool
    {
        return $this->canShowQrCode();
    }

    public function canLogout(): bool
    {
        return $this->isActive();
    }

    public function canTransitionTo(self $newStatus): bool
    {
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

    public function getPollingInterval(): int
    {
        return match ($this) {
            self::CREATING => 2000,
            self::CONNECTING => 2000,
            self::GENERATING_QR => 3000,
            self::PENDING => 5000,
            self::CONNECTED => 30000,
            self::DISCONNECTED => 0,
        };
    }
}
