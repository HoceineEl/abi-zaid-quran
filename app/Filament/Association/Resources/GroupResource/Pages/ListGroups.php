<?php

namespace App\Filament\Association\Resources\GroupResource\Pages;

use App\Filament\Association\Resources\GroupResource;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListGroups extends ListRecords
{
    protected static string $resource = GroupResource::class;


    public function getTabs(): array
    {
        if (!auth()->user()->isTeacher()) {
            return [
                'males' => Tab::make('الذكور')
                    ->query(fn(Builder $query) => $query->whereHas('teacher', fn(Builder $query) => $query->where('sex', 'male'))),
                'females' => Tab::make('الإناث')
                    ->query(fn(Builder $query) => $query->whereHas('teacher', fn(Builder $query) => $query->where('sex', 'female'))),
            ];
        }
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->slideOver()
                ->hidden(fn() => auth()->user()->isTeacher()),
        ];
    }
}
