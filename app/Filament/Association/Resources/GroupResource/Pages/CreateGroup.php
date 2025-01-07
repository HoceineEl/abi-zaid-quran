<?php

namespace App\Filament\Association\Resources\GroupResource\Pages;

use App\Filament\Association\Resources\GroupResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateGroup extends CreateRecord
{
    protected static string $resource = GroupResource::class;

 
}
