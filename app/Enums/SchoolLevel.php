<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum SchoolLevel: string implements HasLabel
{
    case NOT_SCHOOLED = 'not_schooled';
    case PRESCHOOL = 'preschool';
    case PRIMARY_1 = 'primary_1';
    case PRIMARY_2 = 'primary_2';
    case PRIMARY_3 = 'primary_3';
    case PRIMARY_4 = 'primary_4';
    case PRIMARY_5 = 'primary_5';
    case PRIMARY_6 = 'primary_6';
    case MIDDLE_1 = 'middle_1';
    case MIDDLE_2 = 'middle_2';
    case MIDDLE_3 = 'middle_3';
    case COMMON_CORE = 'common_core';
    case BAC_1 = 'bac_1';
    case BAC_2 = 'bac_2';
    case UNIVERSITY = 'university';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::NOT_SCHOOLED => 'غير متمدرس',
            self::PRESCHOOL => 'التعليم الأولي',
            self::PRIMARY_1 => 'الأول ابتدائي',
            self::PRIMARY_2 => 'الثاني ابتدائي',
            self::PRIMARY_3 => 'الثالث ابتدائي',
            self::PRIMARY_4 => 'الرابع ابتدائي',
            self::PRIMARY_5 => 'الخامس ابتدائي',
            self::PRIMARY_6 => 'السادس ابتدائي',
            self::MIDDLE_1 => 'الأولى إعدادي',
            self::MIDDLE_2 => 'الثانية إعدادي',
            self::MIDDLE_3 => 'الثالثة إعدادي',
            self::COMMON_CORE => 'الجذع المشترك',
            self::BAC_1 => 'الأولى باكالوريا',
            self::BAC_2 => 'الثانية باكالوريا',
            self::UNIVERSITY => 'جامعي',
        };
    }

    public static function fromValueOrLabel(string $input): ?self
    {
        return self::tryFrom($input)
            ?? collect(self::cases())->first(fn (self $level): bool => $level->getLabel() === $input);
    }
}
