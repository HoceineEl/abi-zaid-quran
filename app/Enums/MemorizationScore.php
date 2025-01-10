<?php

namespace App\Enums;

enum MemorizationScore: string
{
    case EXCELLENT = 'ممتاز';
    case VERY_GOOD = 'جيد جداً';
    case GOOD = 'جيد';
    case FAIR = 'لا بأس به';
    case POOR = 'لم يحفظ';
    case NOT_MEMORIZED = 'لم يستظهر';

    public function getColor(): string
    {
        return match ($this) {
            self::EXCELLENT => 'success',
            self::VERY_GOOD => 'info',
            self::GOOD => 'warning',
            self::FAIR => 'gray',
            self::POOR, self::NOT_MEMORIZED => 'danger',
        };
    }

    public function getIcon(): string
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
