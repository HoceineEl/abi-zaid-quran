<?php

namespace App\Filament\Resources\StudentDisconnectionResource\Pages;

use App\Filament\Exports\StudentDisconnectionExporter;
use App\Filament\Resources\StudentDisconnectionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListStudentDisconnections extends ListRecords
{
    protected static string $resource = StudentDisconnectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\ExportAction::make()
                ->label('تصدير Excel')
                ->exporter(StudentDisconnectionExporter::class)
                ->icon('heroicon-o-arrow-down-tray'),
        ];
    }
}
