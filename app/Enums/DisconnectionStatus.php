<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum DisconnectionStatus: string implements HasLabel, HasColor, HasIcon
{
    case Disconnected = 'disconnected';
    case Contacted = 'contacted';
    case Responded = 'responded';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Disconnected => 'منقطع',
            self::Contacted => 'تم الاتصال',
            self::Responded => 'تم التواصل',
        };
    }

    public function getColor(): string | array | null
    {
        return match ($this) {
            self::Disconnected => 'danger',
            self::Contacted => 'warning',
            self::Responded => 'info',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Disconnected => 'heroicon-o-exclamation-triangle',
            self::Contacted => 'heroicon-o-phone',
            self::Responded => 'heroicon-o-chat-bubble-left-right',
        };
    }
}
