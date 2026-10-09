<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PaymentType: string implements HasColor, HasLabel
{
    case MONTHLY = 'monthly';
    case REGISTRATION = 'registration';
    case SUMMER_SESSION = 'summer_session';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::MONTHLY => 'الواجب الشهري',
            self::REGISTRATION => 'واجب التسجيل',
            self::SUMMER_SESSION => 'واجب الدورة الصيفية',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::MONTHLY => 'success',
            self::REGISTRATION => 'info',
            self::SUMMER_SESSION => 'warning',
        };
    }
}
