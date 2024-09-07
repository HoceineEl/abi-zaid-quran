<?php

namespace App\Livewire;

use App\Models\Memorizer;
use App\Models\Attendance;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\Attributes\On;

class QrCodeScanner extends Component
{
    public ?string $scannedMemorizerId = null;
    public ?Memorizer $memorizer = null;
    public ?string $message = null;
    public bool $cameraAvailable = true;

    public function mount(): void
    {
        $this->scannedMemorizerId = null;
        $this->memorizer = null;
        $this->message = null;
    }

    #[On('set-scanned-memorizer-id')]
    public function setScannedMemorizerId($id): void
    {
        $this->scannedMemorizerId = $id;
        $this->memorizer = Memorizer::with(['memoGroup'])->find($id);
    }

    #[On('camera-availability')]
    public function setCameraAvailability($available): void
    {
        $this->cameraAvailable = $available;
    }
    public function processScannedData(): void
    {
        if (!$this->memorizer) {
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
        } elseif (!$attendance->check_out_time) {
            $attendance->update(['check_out_time' => now()->toTimeString()]);
            $this->message = 'تم تسجيل الخروج بنجاح';
            $notificationType = 'success';
        } else {
            $this->message = 'تم تسجيل الحضور والخروج مسبقاً';
            $notificationType = 'warning';
        }

        $this->scannedMemorizerId = null;
        $this->memorizer = null;

        Notification::make()
            ->title($this->message)
            ->$notificationType()
            ->send();
    }

    public function render(): View
    {
        return view('livewire.qr-code-scanner');
    }
}
