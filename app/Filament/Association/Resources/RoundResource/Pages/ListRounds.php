<?php

namespace App\Filament\Association\Resources\RoundResource\Pages;

use App\Filament\Association\Resources\RoundResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRounds extends ListRecords
{
    protected static string $resource = RoundResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
