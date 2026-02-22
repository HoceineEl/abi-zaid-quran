<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\ReminderGroupsTable;
use App\Filament\Widgets\ReminderStatsOverview;
use App\Models\Group;
use App\Models\WhatsAppMessageHistory;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Dashboard\Actions\FilterAction;
use Filament\Pages\Dashboard\Concerns\HasFiltersAction;
use Filament\Pages\Page;

class ReminderReport extends Page
{
    use HasFiltersAction;

    protected static ?string $navigationIcon = 'heroicon-o-bell-alert';

    protected static ?string $navigationLabel = 'تقرير التذكيرات';

    protected static ?string $title = 'تقرير التذكيرات اليومي';

    protected static ?string $slug = 'reminder-report';

    protected static string $view = 'filament.pages.reminder-report';

    protected static ?string $navigationGroup = 'التقارير';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if ($user->isAdministrator()) {
            return true;
        }

        return Group::whereHas('managers', fn ($q) => $q->where('users.id', $user->id))->exists();
    }

    public function mount(): void
    {
        $this->filters['date'] = WhatsAppMessageHistory::query()
            ->selectRaw('DATE(created_at) as date')
            ->orderByDesc('created_at')
            ->value('date') ?? now()->toDateString();
    }

    protected function getHeaderActions(): array
    {
        return [
            FilterAction::make()
                ->label('تغيير التاريخ')
                ->modalHeading('اختر التاريخ')
                ->form([
                    DatePicker::make('date')
                        ->label('التاريخ')
                        ->default(fn () => $this->filters['date'] ?? now()->toDateString())
                        ->native(false)
                        ->maxDate(now()),
                ]),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ReminderGroupsTable::class,
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            ReminderStatsOverview::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|string|array
    {
        return 1;
    }

    public function getFooterWidgetsColumns(): int|string|array
    {
        return 1;
    }

    public function getWidgetData(): array
    {
        return [
            'filters' => $this->filters,
        ];
    }
}
