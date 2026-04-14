<?php

namespace App\Filament\Association\Resources\GuardianResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Association\Resources\GuardianResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGuardian extends EditRecord
{
    protected static string $resource = GuardianResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
