<?php

namespace App\Filament\Association\Resources;

use App\Filament\Association\Resources\WhatsAppSessionResource\Pages;
use App\Filament\Resources\WhatsAppSessionResource as BaseWhatsAppSessionResource;
use App\Models\WhatsAppSession;
use Filament\Resources\Resource;
use Filament\Tables\Table;

class WhatsAppSessionResource extends Resource
{
    protected static ?string $model = WhatsAppSession::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationLabel = 'جلسة واتساب';

    protected static ?string $navigationGroup = 'التواصل';

    protected static ?int $navigationSort = 10;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAssociationAccess() ?? false;
    }

    public static function getModelLabel(): string
    {
        return 'جلسة واتساب';
    }

    public static function getPluralModelLabel(): string
    {
        return 'جلسات واتساب';
    }

    public static function table(Table $table): Table
    {
        return BaseWhatsAppSessionResource::table($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWhatsAppSessions::route('/'),
        ];
    }
}
