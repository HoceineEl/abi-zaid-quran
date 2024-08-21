<?php

namespace App\Filament\Association\Resources\MemorizerResource\Pages;

use App\Filament\Association\Resources\MemorizerResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMemorizers extends ListRecords
{
    protected static string $resource = MemorizerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
