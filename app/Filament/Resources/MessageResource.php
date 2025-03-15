<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MessageResource\Pages;
use App\Models\GroupMessageTemplate;
use App\Models\Message;
use Filament\Forms;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MessageResource extends Resource
{
    protected static ?string $model = GroupMessageTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationLabel = 'قوالب الرسائل';

    protected static ?string $modelLabel = 'قالب رسالة';

    protected static ?string $pluralModelLabel = 'قوالب الرسائل';

    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        $actions = [];
        foreach (GroupMessageTemplate::getVariables() as $variable) {
            $actions[] = Action::make('add_' . $variable)
                ->label(GroupMessageTemplate::getVariableLabels()[$variable])
                ->action(function (Set $set, Get $get) use ($variable) {
                    $content = $get('content');
                    $content .= $variable;
                    $set('content', $content);
                })
                ->color('primary');
        }
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('اسم القالب')
                    ->required()
                    ->maxLength(255),
                Section::make('the_content')
                    ->headerActions($actions)
                    ->schema([
                        Forms\Components\Textarea::make('content')
                            ->label('محتوى الرسالة')
                            ->required()
                            ->rows(10)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('اسم القالب')
                    ->searchable(),
                Tables\Columns\TextColumn::make('content')
                    ->label('محتوى الرسالة')
                    ->limit(50),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('تاريخ التحديث')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMessages::route('/'),
            'create' => Pages\CreateMessage::route('/create'),
            'edit' => Pages\EditMessage::route('/{record}/edit'),
        ];
    }
}
