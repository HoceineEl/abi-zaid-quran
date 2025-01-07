<?php

namespace App\Enums;

use Filament\Support\Colors\Color;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasColor;

enum Troubles: string implements HasLabel, HasIcon, HasColor
{
    case INATTENTIVE = 'inattentive';
    case CHATTY = 'chatty';
    case INCOMPLETE_HOMEWORK = 'incomplete_homework';
    case DISRUPTIVE = 'disruptive';
    case PHONE_USE = 'phone_use';
    case RESTLESS = 'restless';
    case UNPREPARED = 'unprepared';
    case TARDY = 'tardy';
    case RUDE = 'rude';
    case LAUGHING = 'laughing';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::INATTENTIVE => 'عدم الانتباه أثناء الدرس',
            self::CHATTY => 'التحدث مع الزملاء بشكل متكرر',
            self::INCOMPLETE_HOMEWORK => 'عدم إكمال الواجبات المطلوبة',
            self::DISRUPTIVE => 'إزعاج الحلقة',
            self::PHONE_USE => 'استخدام الهاتف',
            self::RESTLESS => 'كثرة الحركة والخروج من الحلقة',
            self::UNPREPARED => 'عدم حفظ الواجب',
            self::TARDY => 'التأخر عن موعد الحلقة',
            self::RUDE => 'عدم احترام المعلم أو الزملاء',
            self::LAUGHING => 'كثرة الضحك',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::INATTENTIVE => 'heroicon-o-eye-slash',
            self::CHATTY => 'heroicon-o-chat-bubble-left-right',
            self::INCOMPLETE_HOMEWORK => 'heroicon-o-x-mark',
            self::DISRUPTIVE => 'heroicon-o-exclamation-triangle',
            self::PHONE_USE => 'heroicon-o-device-phone-mobile',
            self::RESTLESS => 'heroicon-o-arrow-path',
            self::UNPREPARED => 'heroicon-o-x-circle',
            self::TARDY => 'heroicon-o-clock',
            self::RUDE => 'heroicon-o-face-frown',
            self::LAUGHING => 'heroicon-o-face-smile',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::INATTENTIVE => Color::Orange,
            self::CHATTY => Color::Yellow,
            self::INCOMPLETE_HOMEWORK => Color::Red,
            self::DISRUPTIVE => Color::Rose,
            self::PHONE_USE => Color::Red,
            self::RESTLESS => Color::Amber,
            self::UNPREPARED => Color::Red,
            self::TARDY => Color::Orange,
            self::RUDE => Color::Red,
            self::LAUGHING => Color::Yellow,
        };
    }
}
