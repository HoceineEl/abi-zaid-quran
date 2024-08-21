<?php

namespace App\Filament\Pages;

use App\Models\Memorizer;
use App\Models\Attendance;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\On;

class ScanAttendance extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-camera';

    protected static string $view = 'filament.association.pages.scan-attendance';
    public $scannedMemorizerId = '';
    public $message = '';
    public ?Memorizer $memorizer = null;

    public function mount(): void
    {
        $this->form->fill();
    }

    public function updatedScannedMemorizerId($value): void
    {
        dd($this->scannedMemorizerId);
        $this->memorizer = Memorizer::with(['memoGroup'])->find($value);
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

        $notificationType = 'success';
        if ($attendance->check_out_time) {
            $this->message = 'يبدو أنك قد سجلت الخروج بالفعل';
            $notificationType = 'warning';
        } elseif ($attendance->created_at?->isToday() && ($attendance->check_out_time === null)) {
            $attendance->update(['check_out_time' => now()->toTimeString()]);
            $this->message = 'تم تسجيل الخروج بنجاح';
            $notificationType = 'success';
        } else {
            $this->message = 'تم تسجيل الحضور بنجاح';
        }

        $this->scannedMemorizerId = '';
        $this->memorizer = null;

        Notification::make()
            ->title($this->message)
            ->$notificationType()
            ->send();
    }

    public function getTitle(): string
    {
        return 'مسح رمز QR للحافظ';
    }
}
