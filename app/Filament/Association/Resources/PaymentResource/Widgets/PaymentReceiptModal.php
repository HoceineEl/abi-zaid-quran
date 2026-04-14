<?php

namespace App\Filament\Association\Resources\PaymentResource\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;

class PaymentReceiptModal extends Widget
{
    protected string $view = 'filament.resources.payment-resource.widgets.payment-receipt-modal';

    public Collection $payments;

    public function mount(Collection $payments)
    {
        $this->payments = $payments;
    }

    #[On('open-modal')]
    public function openModal(): void
    {
        $this->dispatch('open-modal', id: 'payment-receipt');
    }
}
