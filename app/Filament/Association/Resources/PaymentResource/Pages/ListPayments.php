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
            Actions\CreateAction::make(),
        ];
    }
}
