<?php

namespace App\Filament\Association\Resources\PaymentResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Association\Resources\PaymentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPayment extends EditRecord
{
    protected static string $resource = PaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
