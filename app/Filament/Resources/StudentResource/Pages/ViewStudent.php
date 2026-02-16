<?php

namespace App\Filament\Resources\StudentResource\Pages;

use App\Filament\Resources\StudentResource;
use App\Filament\Resources\StudentResource\Widgets\StudentAttendanceChart;
use App\Filament\Resources\StudentResource\Widgets\StudentStatsOverview;
use Filament\Resources\Pages\ViewRecord;

class ViewStudent extends ViewRecord
{
    protected static string $resource = StudentResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            StudentStatsOverview::class,
            StudentAttendanceChart::class,
        ];
    }
}
