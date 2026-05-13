<?php

namespace App\Filament\Resources\StudentResource\Pages;

use App\Filament\Resources\StudentResource;
use App\Filament\Resources\StudentResource\Widgets\StudentAttendanceChart;
use App\Filament\Resources\StudentResource\Widgets\StudentAttendanceSummaryChart;
use App\Filament\Resources\StudentResource\Widgets\StudentStatsOverview;
use App\Filament\Resources\StudentResource\Widgets\StudentWeeklyProgressChart;
use Filament\Resources\Pages\ViewRecord;

class ViewStudent extends ViewRecord
{
    protected static string $resource = StudentResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            StudentStatsOverview::class,          // full width — 6 stat cards
            StudentAttendanceChart::class,        // full width — daily line chart (filterable)
            StudentWeeklyProgressChart::class,    // half width — 12-week bar chart
            StudentAttendanceSummaryChart::class, // half width — doughnut breakdown
        ];
    }

    protected function getHeaderWidgetsColumns(): int|array
    {
        return 2;
    }
}
