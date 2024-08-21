<?php

namespace App\Filament\Pages;

use App\Models\Memorizer;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Hash;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class MemorizerQrCode extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-qr-code';

    protected static string $view = 'filament.pages.memorizer-qr-code';

    public ?array $data = [];

    public ?string $qrCode = null;

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('phone')
                    ->label('رقم الهاتف')
                    ->tel()
                    ->required(),
                TextInput::make('password')
                    ->label('كلمة المرور')
                    ->password()
                    ->required(),
            ])
            ->statePath('data');
    }

    public function generateQrCode(): void
    {
        $data = $this->form->getState();

        $memorizer = Memorizer::where('phone', $data['phone'])->first();

        if (!$memorizer || !Hash::check($data['password'], $memorizer->password)) {
            Notification::make()
                ->title('رقم الهاتف أو كلمة المرور غير صحيحة')
                ->danger()
                ->send();

            $this->qrCode = null;
            return;
        }

        $this->qrCode = $this->generateQrCodeSvg($memorizer);

        Notification::make()
            ->title('تم إنشاء رمز QR بنجاح')
            ->success()
            ->send();
    }

    private function generateQrCodeSvg($memorizer): string
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

    public function getTitle(): string
    {
        return 'رمز QR للحافظ';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false; // Set to true if you want this page to appear in the navigation
    }
}
