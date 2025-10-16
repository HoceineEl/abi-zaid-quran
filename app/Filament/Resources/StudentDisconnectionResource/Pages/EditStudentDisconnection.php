<?php

namespace App\Filament\Resources\StudentDisconnectionResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\StudentDisconnectionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditStudentDisconnection extends EditRecord
{
    protected static string $resource = StudentDisconnectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): ?string
    {
        return $this->getResource()::getUrl('index');
    }
}
