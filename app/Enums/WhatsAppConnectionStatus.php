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
}
