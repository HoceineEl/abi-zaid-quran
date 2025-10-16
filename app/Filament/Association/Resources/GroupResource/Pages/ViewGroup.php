<?php

namespace App\Filament\Association\Resources\GroupResource\Pages;

use Filament\Actions\EditAction;
use App\Filament\Association\Resources\GroupResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewGroup extends ViewRecord
{
    protected static string $resource = GroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->hidden(fn() => auth()->user()->isTeacher()),
        ];
    }
}
