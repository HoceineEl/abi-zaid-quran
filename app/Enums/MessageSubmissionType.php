<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Contracts\HasDescription;

enum MessageSubmissionType: string implements HasLabel, HasColor, HasIcon, HasDescription
{
    case Media = 'media';
    case Text = 'text';
    case Both = 'both';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Media => 'وسائط صوتية فقط',
            self::Text => 'رسائل نصية فقط',
            self::Both => 'كلاهما',
        };
    }

    public function getColor(): string | array | null
    {
        return match ($this) {
            self::Media => 'success',
            self::Text => 'warning',
            self::Both => 'primary',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Media => 'heroicon-o-photo',
            self::Text => 'heroicon-o-chat-bubble-left-right',
            self::Both => 'heroicon-o-check-circle',
        };
    }

    public function getDescription(): ?string
    {
        return match ($this) {
            self::Media => 'سيتم فقط إعتبار الطلاب الذين أرسلوا وسائط صوتية كحاضرين',
            self::Text => 'سيتم فقط إعتبار الطلاب الذين أرسلوا رسائل نصية كحاضرين',
            self::Both => 'سيتم إعتبار الطلاب الذين أرسلوا وسائط صوتية أو رسائل نصية كحاضرين',
        };
    }
}
