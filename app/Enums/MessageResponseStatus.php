<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum MessageResponseStatus: string implements HasLabel, HasColor, HasIcon
{
    case NotContacted = 'not_contacted';
    case ReminderMessage = 'reminder_message';
    case WarningMessage = 'warning_message';
    case QuestionnaireMessage = 'questionnaire_message';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::NotContacted => 'لم يتم التواصل',
            self::ReminderMessage => 'الرسالة التذكيرية',
            self::WarningMessage => 'الرسالة الإندارية',
            self::QuestionnaireMessage => 'رسالة استفسارية',
        };
    }

    public function getColor(): string | array | null
    {
        return match ($this) {
            self::NotContacted => 'gray',
            self::ReminderMessage => 'info',
            self::WarningMessage => 'warning',
            self::QuestionnaireMessage => 'success',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::NotContacted => 'heroicon-o-phone-x-mark',
            self::ReminderMessage => 'heroicon-o-bell',
            self::WarningMessage => 'heroicon-o-exclamation-circle',
            self::QuestionnaireMessage => 'heroicon-o-clipboard-document-list',
        };
    }
}

