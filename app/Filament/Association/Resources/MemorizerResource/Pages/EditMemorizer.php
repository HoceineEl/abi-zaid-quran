<?php

namespace App\Filament\Association\Resources\MemorizerResource\Pages;

use App\Filament\Association\Resources\MemorizerResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMemorizer extends EditRecord
{
    protected static string $resource = MemorizerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
