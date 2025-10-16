<?php

namespace App\Enums;

enum WhatsAppMessageStatus: string
{
    case QUEUED = 'queued';
    case SENT = 'sent';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::QUEUED => 'Queued',
            self::SENT => 'Sent',
            self::FAILED => 'Failed',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::QUEUED => 'warning',
            self::SENT => 'success',
            self::FAILED => 'danger',
            self::CANCELLED => 'gray',
        };
    }

    public function canRetry(): bool
    {
        return $this === self::FAILED;
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::SENT, self::CANCELLED]);
    }
}
