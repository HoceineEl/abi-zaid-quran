<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum MessageResponseStatus: string implements HasLabel, HasColor, HasIcon
{
    case Yes = 'yes';
    case No = 'no';
    case NotContacted = 'not_contacted';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Yes => 'نعم',
            self::No => 'لا',
            self::NotContacted => 'لم يتم التواصل',
        };
    }

    public function getColor(): string | array | null
    {
        return match ($this) {
            self::Yes => 'success',
            self::No => 'danger',
            self::NotContacted => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Yes => 'heroicon-o-check-circle',
            self::No => 'heroicon-o-x-circle',
            self::NotContacted => 'heroicon-o-phone-x-mark',
        };
    }
}
