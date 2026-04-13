<?php

namespace App\Filament\Association\Resources\WhatsAppSessionResource\Pages;

use App\Filament\Association\Resources\WhatsAppSessionResource;
use App\Filament\Resources\WhatsAppSessionResource\Pages\ListWhatsAppSessions as BaseListWhatsAppSessions;

class ListWhatsAppSessions extends BaseListWhatsAppSessions
{
    protected static string $resource = WhatsAppSessionResource::class;
}
