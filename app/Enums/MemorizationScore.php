<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Contracts\HasIcon;

enum MemorizationScore: string implements HasColor, HasLabel, HasIcon
{
    case EXCELLENT = 'excellent';
    case VERY_GOOD = 'very_good';
    case GOOD = 'good';
    case FAIR = 'fair';
    case POOR = 'poor';
    case NOT_MEMORIZED = 'not_memorized';

    public function getColor(): string|array|null
    {

        return match ($this) {
            self::EXCELLENT => 'success',
            self::VERY_GOOD => 'info',
            self::GOOD => 'warning',
            self::FAIR => 'gray',
            self::POOR, self::NOT_MEMORIZED => 'danger',
        };
    }

    public function getLabel(): ?string
    {
        return match ($this) {
            self::EXCELLENT => 'ممتاز',
            self::VERY_GOOD => 'جيد جداً',
            self::GOOD => 'جيد',
            self::FAIR => 'لا بأس به',
            self::POOR => 'لم يحفظ',
            self::NOT_MEMORIZED => 'لم يستظهر',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::EXCELLENT => 'heroicon-o-star',
            self::VERY_GOOD => 'heroicon-o-hand-thumb-up',
            self::GOOD => 'heroicon-o-check-circle',
            self::FAIR => 'heroicon-o-minus-circle',
            self::POOR, self::NOT_MEMORIZED => 'heroicon-o-x-circle',
        };
    }
}
