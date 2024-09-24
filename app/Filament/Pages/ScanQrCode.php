<?php

namespace App\Filament\Pages;

use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Contracts\Support\Htmlable;

class ScanQrCode extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-qr-code';

    protected static string $view = 'filament.pages.scan-qr-code';

    public function getMaxContentWidth(): MaxWidth
    {
        return MaxWidth::Full;
    }

    public function getTitle(): string|Htmlable
    {
        return 'مسح رمز QR للطلاب';
    }
    public static function shouldRegisterNavigation(): bool
    {
        return \Filament\Facades\Filament::getCurrentPanel()->getId() === 'association';
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
