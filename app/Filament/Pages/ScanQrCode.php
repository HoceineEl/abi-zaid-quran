<?php

namespace App\Filament\Pages;

use Filament\Support\Enums\Width;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class ScanQrCode extends Page
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-qr-code';

    protected string $view = 'filament.pages.scan-qr-code';

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    public function getTitle(): string|Htmlable
    {
        return 'مسح رمز QR للطلاب';
    }
    public static function shouldRegisterNavigation(): bool
    {
        return Filament::getCurrentOrDefaultPanel()->getId() === 'association';
    }
    public function getHeading(): string
    {
        return 'مسح رمز QR للطلاب';
    }

    public static function getNavigationLabel(): string
    {
        return 'مسح رمز QR للطلاب';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'إدارة الحضور';
    }
}
