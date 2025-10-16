<?php

namespace App\Filament\Association\Resources\PaymentResource\Pages;

use App\Filament\Association\Resources\PaymentResource;
use App\Filament\Association\Resources\PaymentResource\Widgets\PaymentReceiptModal;
use App\Models\Payment;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Filament\Notifications\Notification;
use App\Traits\GoToIndex;
use Illuminate\Support\Collection;
use Filament\Actions\Action as FilamentAction;

class CreatePayment extends CreateRecord
{
    protected static string $resource = PaymentResource::class;
    use GoToIndex;

    public Collection $createdPayments;

    protected function getCreateFormAction(): FilamentAction
    {
        return parent::getCreateFormAction()
            ->after(function () {
                $this->showReceipt();
            });
    }

    protected function showReceipt(): void
    {
        $this->dispatch('open-modal');
    }

    protected function handleRecordCreation(array $data): Model
    {
        $data = $this->data;
        $this->createdPayments = new Collection();

        foreach ($data['payments'] as $payment) {
            $this->createdPayments->push(
                Payment::create([
                    'memorizer_id' => $data['memorizer_id'],
                    'amount' => $payment['paid_amount_month'],
                    'payment_date' => $payment['payment_date']
                ])
            );
        }

        $paymentIds = $this->createdPayments->pluck('id')->join(',');
        $receiptUrl = route('payments.receipt', ['payments' => $paymentIds]);

        Notification::make()
            ->title('تم إضافة الدفعات بنجاح')
            ->success()
            ->persistent()
            ->actions([
                FilamentAction::make('print_receipt')
                    ->label('طباعة الإيصال')
                    ->icon('heroicon-o-printer')
                    ->button()
                    ->color('success')
                    ->url($receiptUrl, shouldOpenInNewTab: true)
            ])
            ->send();

        return $this->createdPayments->first();
    }

    protected function getFooterWidgets(): array
    {
        return [
            PaymentReceiptModal::make([
                'payments' => $this->createdPayments ?? collect(),
            ]),
        ];
    }
}
