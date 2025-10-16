<?php

namespace App\Classes;

use Filament\Resources\Resource;

abstract class BaseResource extends Resource
{
    protected static string|\UnitEnum|null $navigationGroup = null;

    protected static ?int $navigationSort = null;

    protected static bool $isLazy = false;
}
