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
    case ReminderMessage = 'reminder_message';
    case WarningMessage = 'warning_message';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Yes => 'نعم',
            self::No => 'لا',
            self::NotContacted => 'لم يتم التواصل',
            self::ReminderMessage => 'الرسالة التذكيرية',
            self::WarningMessage => 'الرسالة الإندارية',
        };
    }

    public function getColor(): string | array | null
    {
        return match ($this) {
            self::Yes => 'success',
            self::No => 'danger',
            self::NotContacted => 'gray',
            self::ReminderMessage => 'info',
            self::WarningMessage => 'warning',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Yes => 'heroicon-o-check-circle',
            self::No => 'heroicon-o-x-circle',
            self::NotContacted => 'heroicon-o-phone-x-mark',
            self::ReminderMessage => 'heroicon-o-bell',
            self::WarningMessage => 'heroicon-o-exclamation-circle',
        };
    }
}
