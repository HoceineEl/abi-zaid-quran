<?php

namespace App\Filament\Association\Resources\GuardianResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Association\Resources\GuardianResource;
use Filament\Actions;
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
