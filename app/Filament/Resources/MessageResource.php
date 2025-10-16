<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MessageResource\Pages\CreateMessage;
use App\Filament\Resources\MessageResource\Pages\EditMessage;
use App\Filament\Resources\MessageResource\Pages\ListMessages;
use App\Models\Group;
use App\Models\GroupMessageTemplate;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MessageResource extends Resource
{
    protected static ?string $model = GroupMessageTemplate::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationLabel = 'قوالب الرسائل';

    protected static ?string $modelLabel = 'قالب رسالة';

    protected static ?string $pluralModelLabel = 'قوالب الرسائل';

    protected static ?int $navigationSort = 5;

    public static function canAccess(): bool
    {
        return auth()->user()->isAdministrator();
    }

    public static function form(Schema $schema): Schema
    {
        $actions = [];
        foreach (GroupMessageTemplate::getVariables() as $variable) {
            $actions[] = Action::make('add_'.$variable)
                ->label(GroupMessageTemplate::getVariableLabels()[$variable])
                ->action(function (Set $set, Get $get) use ($variable) {
                    $content = $get('content');
                    $content .= $variable;
                    $set('content', $content);
                })
                ->color('primary');
        }

        return $schema
            ->components([
                TextInput::make('name')
                    ->label('اسم القالب')
                    ->required()
                    ->maxLength(255),
                Section::make('the_content')
                    ->headerActions($actions)
                    ->schema([
                        Textarea::make('content')
                            ->label('محتوى الرسالة')
                            ->required()
                            ->rows(10)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                Section::make('attached_groups')
                    ->label('المجموعات المرتبطة')
                    ->description('المجموعات التي تم ربط هذا القالب بها')
                    ->schema([
                        Placeholder::make('groups_display')
                            ->label('المجموعات المرتبطة')
                            ->content(function ($record) {
                                if (! $record || ! $record->groups()->exists()) {
                                    return 'لا توجد مجموعات مرتبطة';
                                }

                                $groups = $record->groups()->get();
                                $groupNames = $groups->map(function ($group) {
                                    $isDefault = $group->pivot->is_default ? ' (افتراضي)' : '';

                                    return $group->name.$isDefault;
                                })->implode('، ');

                                return $groupNames;
                            })
                            ->columnSpanFull(),
                    ])
                    ->visible(fn ($record) => $record && $record->exists)
                    ->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('اسم القالب')
                    ->searchable(),
                TextColumn::make('content')
                    ->label('محتوى الرسالة')
                    ->limit(50),
                TextColumn::make('groups.name')
                    ->label('المجموعات المرتبطة')
                    ->badge()
                    ->color('primary')
                    ->listWithLineBreaks()
                    ->limitList(3)
                    ->expandableLimitedList()
                    ->default('لا يوجد'),
                TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('تاريخ التحديث')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                EditAction::make(),
                ActionGroup::make([
                    Action::make('view_groups')
                        ->label('عرض المجموعات المرتبطة')
                        ->icon('heroicon-o-eye')
                        ->color('info')
                        ->modalHeading('المجموعات المرتبطة')
                        ->modalWidth('xl')
                        ->schema([
                            Section::make('المجموعات المرتبطة')
                                ->schema([
                                    TextEntry::make('groups.name')
                                        ->label('المجموعات')
                                        ->listWithLineBreaks()
                                        ->badge()
                                        ->color('primary')
                                        ->icon('heroicon-o-user-group'),
                                ])
                                ->columns(1),
                        ]),
                    Action::make('attach_to_group')
                        ->label('ربط بمجموعات')
                        ->icon('heroicon-o-link')
                        ->color('success')
                        ->schema(fn (GroupMessageTemplate $record) => [
                            Select::make('groups')
                                ->label('المجموعات')
                                ->options(
                                    Group::query()
                                        ->whereDoesntHave('messageTemplates', fn ($q) => $q->where('group_message_templates.id', $record->id))
                                        ->pluck('name', 'id')
                                        ->toArray()
                                )
                                ->multiple()
                                ->searchable()
                                ->required()
                                ->helperText('اختر المجموعات التي تريد ربط هذا القالب بها'),
                            Toggle::make('set_as_default')
                                ->label('تعيين كقالب افتراضي')
                                ->default(false)
                                ->helperText('سيتم تعيين هذا القالب كافتراضي للمجموعات المحددة'),
                        ])
                        ->action(function (GroupMessageTemplate $record, array $data) {
                            foreach ($data['groups'] as $groupId) {
                                $group = Group::find($groupId);
                                if ($group) {
                                    if ($data['set_as_default']) {
                                        // First unset any existing default templates for this group
                                        $group->messageTemplates()->updateExistingPivot(
                                            $group->messageTemplates()->wherePivot('is_default', true)->pluck('group_message_templates.id'),
                                            ['is_default' => false]
                                        );
                                        // Set this template as default
                                        $group->messageTemplates()->syncWithoutDetaching([
                                            $record->id => ['is_default' => true],
                                        ]);
                                    } else {
                                        // Just attach without making it default
                                        $group->messageTemplates()->syncWithoutDetaching([
                                            $record->id => ['is_default' => false],
                                        ]);
                                    }
                                }
                            }

                            $groupCount = count($data['groups']);
                            $message = $data['set_as_default']
                                ? "تم ربط القالب وتعيينه كافتراضي لـ {$groupCount} مجموعة"
                                : "تم ربط القالب لـ {$groupCount} مجموعة";

                            Notification::make()
                                ->title('تم ربط القالب بنجاح')
                                ->body($message)
                                ->success()
                                ->send();
                        }),
                    Action::make('detach_from_group')
                        ->label('إزالة من مجموعات')
                        ->icon('heroicon-o-minus-circle')
                        ->color('danger')
                        ->visible(fn (GroupMessageTemplate $record) => $record->groups()->exists())
                        ->schema(fn (GroupMessageTemplate $record) => [
                            Select::make('groups')
                                ->label('المجموعات')
                                ->options($record->groups()->pluck('name', 'id')->toArray())
                                ->multiple()
                                ->searchable()
                                ->required()
                                ->helperText('اختر المجموعات التي تريد إزالة هذا القالب منها'),
                        ])
                        ->action(function (GroupMessageTemplate $record, array $data) {
                            $record->groups()->detach($data['groups']);

                            $groupCount = count($data['groups']);
                            Notification::make()
                                ->title('تم إزالة القالب من المجموعات')
                                ->body("تم إزالة القالب من {$groupCount} مجموعة")
                                ->success()
                                ->send();
                        }),
                ])
                    ->label('إدارة المجموعات')
                    ->icon('heroicon-o-user-group'),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
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
            'index' => ListMessages::route('/'),
            'create' => CreateMessage::route('/create'),
            'edit' => EditMessage::route('/{record}/edit'),
        ];
    }
}
