<?php

namespace App\Filament\Resources\ProgressResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\ProgressResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProgress extends ListRecords
{
    protected static string $resource = ProgressResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
