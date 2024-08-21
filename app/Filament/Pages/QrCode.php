<?php

namespace App\Filament\Pages;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Filament\Pages\Page;

class QrCode extends Page
{
    protected static string $view = 'filament.student.pages.qr-code';

    public function getQrCode(): string
    {
        $data = json_encode([
            'student_id' => auth()->id(),
            'name' => auth()->user()->name,
        ]);

        $renderer = new ImageRenderer(
            new RendererStyle(400),
            new SvgImageBackEnd()
        );
        $writer = new Writer($renderer);
        $svg = $writer->writeString($data);

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    public static function getNavigationLabel(): string
    {
        return 'رمز QR الخاص بك';
    }

    public function getTitle(): string
    {
        return 'رمز QR الخاص بك';
    }
}
