<?php

namespace App\Filament\Association\Resources\MemorizerResource\Pages;

use App\Filament\Association\Resources\MemorizerResource;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListMemorizers extends ListRecords
{
    protected static string $resource = MemorizerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('الجميع')
                ->query(fn(Builder $query) => $query),
            'males' => Tab::make('الذكور')
                ->query(fn(Builder $query) => $query->whereHas('teacher', fn($query) => $query->where('sex', 'male'))),
            'females' => Tab::make('الإناث')
                ->query(fn(Builder $query) => $query->whereHas('teacher', fn($query) => $query->where('sex', 'female'))),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'males';
    }
}
