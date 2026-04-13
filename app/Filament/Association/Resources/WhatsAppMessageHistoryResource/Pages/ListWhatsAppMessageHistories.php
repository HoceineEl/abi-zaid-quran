<?php

namespace App\Filament\Association\Resources\WhatsAppMessageHistoryResource\Pages;

use App\Filament\Association\Resources\WhatsAppMessageHistoryResource;
use Filament\Resources\Pages\ListRecords;

class ListWhatsAppMessageHistories extends ListRecords
{
    protected static string $resource = WhatsAppMessageHistoryResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
