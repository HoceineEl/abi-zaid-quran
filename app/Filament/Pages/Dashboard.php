<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\DatePicker;
use Filament\Pages\Dashboard\Actions\FilterAction;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersAction;

class Dashboard extends BaseDashboard
{
    use HasFiltersAction;

    protected function getHeaderActions(): array
    {
        return [
            FilterAction::make()
                ->modalDescription('اختر الفترة المراد عرض الإحصائيات عليها')
                ->schema([
                    DatePicker::make('date_from')
                        ->label('من تاريخ')
                        ->default(now()->startOfMonth())
                        ->helperText('سيتم عرض جميع الإحصائيات والبيانات بدءاً من هذا التاريخ')
                        ->required(),
                    DatePicker::make('date_to')
                        ->label('إلى تاريخ')
                        ->default(now()->endOfMonth())
                        ->helperText('سيتم عرض جميع الإحصائيات والبيانات حتى نهاية هذا التاريخ')
                        ->required(),
                ]),
        ];
    }
}
