<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Colors\Color;

enum MemorizationScore: string implements HasLabel, HasIcon, HasColor
{
    case EXCELLENT = 'ممتاز';
    case VERY_GOOD = 'حسن';
    case GOOD = 'جيد';
    case FAIR = 'لا بأس به';
    case NOT_MEMORIZED = 'لم يحفظ';
    case NOT_REVIEWED = 'لم يستظهر';

    public function getLabel(): ?string
    {
        return $this->value;
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::EXCELLENT => 'heroicon-o-check-circle',
            self::VERY_GOOD => 'heroicon-o-check',
            self::GOOD => 'heroicon-o-check',
            self::FAIR => 'heroicon-o-exclamation-circle',
            self::NOT_MEMORIZED => 'heroicon-o-x-circle',
            self::NOT_REVIEWED => 'heroicon-o-x-circle',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::EXCELLENT => Color::Emerald,
            self::VERY_GOOD => Color::Green,
            self::GOOD => Color::Blue,
            self::FAIR => Color::Amber,
            self::NOT_MEMORIZED => Color::Red,
            self::NOT_REVIEWED => Color::Rose,
        };
    }
}
