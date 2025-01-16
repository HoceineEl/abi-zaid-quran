<?php

namespace App\Filament\Association\Resources\PaymentResource\Pages;

use App\Filament\Association\Resources\PaymentResource;
use App\Models\Payment;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Filament\Notifications\Notification;
use App\Traits\GoToIndex;
class CreatePayment extends CreateRecord
{
    protected static string $resource = PaymentResource::class;
    use GoToIndex;
    protected function handleRecordCreation(array $data): Model
    {
        $data = $this->data;
        foreach ($data['payments'] as $payment) {
            \App\Models\Payment::create([
                'memorizer_id' => $data['memorizer_id'],
                'amount' => $payment['paid_amount_month'],
                'payment_date' => $payment['payment_date']
            ]);
        }

        Notification::make()
            ->title('تم إضافة الدفعات بنجاح')
            ->success()
            ->send();

        return Payment::latest()->first();
    }
}
