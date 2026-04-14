<?php

namespace App\Filament\Clusters;

use Filament\Clusters\Cluster;

class Communication extends Cluster
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationLabel = 'Communication';

    protected static ?int $navigationSort = 50;
}