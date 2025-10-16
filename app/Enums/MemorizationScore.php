<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum MemorizationScore: string implements HasColor, HasIcon, HasLabel
{
    case EXCELLENT = 'excellent';
    case GOOD = 'good';
    case VERY_GOOD = 'very_good';
    case FAIR = 'fair';
    case ACCEPTABLE = 'acceptable';
    case POOR = 'poor';
    case NOT_MEMORIZED = 'not_memorized';

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::EXCELLENT => 'success',
            self::GOOD => 'success',
            self::VERY_GOOD => 'info',
            self::FAIR => 'warning',
            self::ACCEPTABLE => 'gray',
            self::POOR, self::NOT_MEMORIZED => 'danger',
        };
    }

    public function getLabel(): ?string
    {
        return match ($this) {
            self::EXCELLENT => 'ممتاز',
            self::GOOD => 'جيد',
            self::VERY_GOOD => 'حسن',
            self::FAIR => 'مستحسن',
            self::ACCEPTABLE => 'لا بأس به',
            self::POOR => 'لم يحفظ',
            self::NOT_MEMORIZED => 'لم يستظهر',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::EXCELLENT => 'tabler-star-filled',
            self::GOOD => 'tabler-thumb-up',
            self::VERY_GOOD => 'tabler-award',
            self::FAIR => 'tabler-circle-check',
            self::ACCEPTABLE => 'tabler-mood-neutral',
            self::POOR => 'tabler-thumb-down',
            self::NOT_MEMORIZED => 'tabler-circle-x',
        };
    }
}
