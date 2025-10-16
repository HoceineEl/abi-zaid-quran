<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum Days: string implements HasLabel
{
    case MONDAY = 'monday';
    case TUESDAY = 'tuesday';
    case WEDNESDAY = 'wednesday';
    case THURSDAY = 'thursday';
    case FRIDAY = 'friday';
    case SATURDAY = 'saturday';
    case SUNDAY = 'sunday';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::MONDAY => 'الإثنين',
            self::TUESDAY => 'الثلاثاء',
            self::WEDNESDAY => 'الأربعاء',
            self::THURSDAY => 'الخميس',
            self::FRIDAY => 'الجمعة',
            self::SATURDAY => 'السبت',
            self::SUNDAY => 'الأحد',
        };
    }

    public static function toArray(): array
    {
        return array_combine(
            array_column(self::cases(), 'value'),
            array_map(fn ($case) => $case->getLabel(), self::cases())
        );
    }
}
