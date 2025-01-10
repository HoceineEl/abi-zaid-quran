<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Actions\FilterAction;
use Filament\Pages\Dashboard\Concerns\HasFiltersAction;

class Dashboard extends BaseDashboard
{
    use HasFiltersAction;

    protected function getHeaderActions(): array
    {
        return [
            FilterAction::make()
                ->form([
                    DatePicker::make('date_from')
                        ->label('من تاريخ')
                        ->default(now()->startOfYear())
                        ->required(),
                    DatePicker::make('date_to')
                        ->label('إلى تاريخ')
                        ->default(now())
                        ->required(),
                ]),
        ];
    }
}
