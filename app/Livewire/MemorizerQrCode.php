<?php

namespace App\Livewire;

use App\Models\Memorizer;
use Livewire\Component;
use Illuminate\Support\Facades\Hash;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class MemorizerQrCode extends Component
{

    public $phone = '';
    public $password = '';
    public $qrCode = null;
    public $error = null;

    public function generateQrCode()
    {
        $this->validate([
            'phone' => 'required',
            'password' => 'required',
        ]);

        $memorizer = Memorizer::where('phone', $this->phone)->first();

        if (!$memorizer || !Hash::check($this->password, $memorizer->password)) {
            $this->error = 'رقم الهاتف أو كلمة المرور غير صحيحة';
            $this->qrCode = null;
            return;
        }

        $this->error = null;
        $this->qrCode = $this->generateQrCodeSvg($memorizer);
    }

    private function generateQrCodeSvg($memorizer)
    {
        $data = json_encode([
            'memorizer_id' => $memorizer->id,
            'name' => $memorizer->name,
        ]);

        $renderer = new ImageRenderer(
            new RendererStyle(400),
            new SvgImageBackEnd()
        );
        $writer = new Writer($renderer);
        $svg = $writer->writeString($data);

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.memorizer-qr-code')
            ->layout('layouts.guest');
    }
}
