<?php

namespace App\Filament\Association\Resources\GuardianResource\Pages;

use App\Filament\Association\Resources\GuardianResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGuardians extends ListRecords
{
    protected static string $resource = GuardianResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
