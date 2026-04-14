<?php

namespace App\Filament\Association\Resources\TeacherResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Schemas\Components\Tabs\Tab;
use App\Filament\Association\Resources\TeacherResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListTeachers extends ListRecords
{
    protected static string $resource = TeacherResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }


    public function getTabs(): array
    {
        return [
            'male' => Tab::make('ذكور')
                ->label('رجال')
                ->query(fn(Builder $query) => $query->where('sex', 'male')),
            'female' => Tab::make('أناث')
                ->label('نساء')
                ->query(fn(Builder $query) => $query->where('sex', 'female')),
        ];
    }
}
