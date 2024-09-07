<?php

namespace App\Filament\Pages;

use App\Livewire\QrCodeScanner;
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

    public function getTitle(): string | Htmlable
    {
        return 'مسح رمز QR للحافظ';
    }

    public function getHeading(): string
    {
        return 'مسح رمز QR للحافظ';
    }

    public static function getNavigationLabel(): string
    {
        return 'مسح رمز QR للحافظ';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'إدارة الحضور';
    }
}
