<?php

namespace App\Filament\Association\Resources\PaymentResource\Pages;

use App\Filament\Association\Resources\PaymentResource;
use App\Traits\GoToIndex;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPayments extends ListRecords
{
    protected static string $resource = PaymentResource::class;

    use GoToIndex;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
