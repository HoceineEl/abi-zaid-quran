<?php

namespace App\Filament\Association\Resources\MemorizerResource\Pages;

use App\Enums\MemorizationScore;
use App\Filament\Association\Resources\MemorizerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ListMemorizers extends ListRecords
{
    protected static string $resource = MemorizerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('الجميع')
                ->icon('heroicon-o-users')
                ->badge(fn () => $this->getModel()::count())
                ->query(fn (Builder $query) => $query),
            'males' => Tab::make('الذكور')
                ->icon('heroicon-o-user')
                ->badgeColor('blue')
                ->badge(fn () => $this->getModel()::whereHas('teacher', fn ($q) => $q->where('sex', 'male'))->count())
                ->query(fn (Builder $query) => $query->whereHas('teacher', fn ($query) => $query->where('sex', 'male'))),
            'females' => Tab::make('الإناث')
                ->icon('heroicon-o-user')
                ->badgeColor('pink')
                ->badge(fn () => $this->getModel()::whereHas('teacher', fn ($q) => $q->where('sex', 'female'))->count())
                ->query(fn (Builder $query) => $query->whereHas('teacher', fn ($query) => $query->where('sex', 'female'))),
            'troublemakers' => Tab::make('المشاغبون')
                ->icon('heroicon-o-exclamation-triangle')
                ->badgeColor('danger')
                ->badge(fn () => $this->getModel()::whereHas('hasTroubles')->count())
                ->query(fn (Builder $query) => $query->whereHas('hasTroubles', fn ($query) => $query->select(DB::raw('COUNT(*) as trouble_count'))->having('trouble_count', '>=', 1))),
            'good_students' => Tab::make('المجدون')
                ->icon('heroicon-o-star')
                ->badgeColor('success')
                ->badge(fn () => $this->getModel()::whereHas('hasTroubles', fn ($q) => $q->select(DB::raw('COUNT(*) as trouble_count'))->having('trouble_count', '<', 3))
                    ->whereHas('attendances', fn ($q) => $q->whereIn('score', [
                        MemorizationScore::EXCELLENT->value,
                        MemorizationScore::VERY_GOOD->value,
                        MemorizationScore::GOOD->value,
                    ]))->count())
                ->query(fn (Builder $query) => $query
                    ->whereHas('hasTroubles', fn ($query) => $query
                        ->select(DB::raw('COUNT(*) as trouble_count'))
                        ->having('trouble_count', '<', 3)
                    )
                    ->whereHas('attendances', fn ($query) => $query
                        ->whereIn('score', [
                            MemorizationScore::EXCELLENT->value,
                            MemorizationScore::VERY_GOOD->value,
                            MemorizationScore::GOOD->value,
                        ])
                    )),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'males';
    }
}
