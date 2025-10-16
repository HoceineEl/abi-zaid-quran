<?php

namespace App\Livewire;

use App\Models\Attendance;
use App\Models\Memorizer;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class QrCodeScanner extends Component
{
    public ?string $scannedMemorizerId = null;

    public ?Memorizer $memorizer = null;

    public ?string $message = null;

    public bool $cameraAvailable = true;

    public bool $autoRegister = true;

    public function mount(): void
    {
        $this->scannedMemorizerId = null;
        $this->memorizer = null;
        $this->message = null;
    }

    #[On('set-scanned-memorizer-id')]
    public function setScannedMemorizerId($id): void
    {
        $id = json_decode($id, true)['memorizer_id'];
        $this->scannedMemorizerId = $id;
        $this->memorizer = Memorizer::with(['group'])->find($id);

        if ($this->autoRegister) {
            $this->processScannedData();
        }
    }

    #[On('camera-availability')]
    public function setCameraAvailability($available): void
    {
        $this->cameraAvailable = $available;
    }

    public function processScannedData(): void
    {
        if (! $this->memorizer) {
            $this->message = 'رمز QR غير صالح';

            return;
        }

        $attendance = Attendance::firstOrCreate([
            'memorizer_id' => $this->memorizer->id,
            'date' => now()->toDateString(),
        ], [
            'check_in_time' => now()->toTimeString(),
        ]);

        if ($attendance->wasRecentlyCreated) {
            $this->message = 'تم تسجيل الحضور بنجاح';
            $notificationType = 'success';

            Notification::make('attendance_notification')
                ->title($this->message)
                ->body(fn () => $this->memorizer->name)
                ->success()
                ->send();
        }
    }

    public function render(): View
    {
        return view('livewire.qr-code-scanner');
    }
}
