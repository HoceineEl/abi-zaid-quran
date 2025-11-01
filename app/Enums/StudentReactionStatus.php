<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum StudentReactionStatus: string implements HasLabel, HasColor, HasIcon
{
    case ReactedToReminder = 'reacted_to_reminder';
    case ReactedToWarning = 'reacted_to_warning';
    case PositiveResponse = 'positive_response';
    case NegativeResponse = 'negative_response';
    case NoResponse = 'no_response';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::ReactedToReminder => 'تفاعل مع التذكير',
            self::ReactedToWarning => 'تفاعل مع الإنذار',
            self::PositiveResponse => 'استجابة إيجابية',
            self::NegativeResponse => 'استجابة سلبية',
            self::NoResponse => 'لم يستجب',
        };
    }

    public function getColor(): string | array | null
    {
        return match ($this) {
            self::ReactedToReminder => 'info',
            self::ReactedToWarning => 'warning',
            self::PositiveResponse => 'success',
            self::NegativeResponse => 'danger',
            self::NoResponse => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::ReactedToReminder => 'heroicon-o-bell',
            self::ReactedToWarning => 'heroicon-o-exclamation-circle',
            self::PositiveResponse => 'heroicon-o-check-circle',
            self::NegativeResponse => 'heroicon-o-x-circle',
            self::NoResponse => 'heroicon-o-minus-circle',
        };
    }
}
