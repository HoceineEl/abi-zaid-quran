<?php

namespace App\Filament\Association\Resources\PaymentResource\Pages;

use App\Filament\Association\Resources\PaymentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use App\Traits\GoToIndex;
use Filament\Notifications\Notification;

class ListPayments extends ListRecords
{
    protected static string $resource = PaymentResource::class;

    use GoToIndex;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->modal()->slideOver()
                ->action(function (array $data) {
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
                }),
        ];
    }
}
