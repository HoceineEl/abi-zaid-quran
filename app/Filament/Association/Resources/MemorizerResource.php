<?php

namespace App\Filament\Association\Resources;

use App\Filament\Association\Resources\GroupResource\RelationManagers\MemorizersRelationManager;
use App\Filament\Association\Resources\MemorizerResource\Pages;
use App\Filament\Association\Resources\MemorizerResource\RelationManagers\PaymentsRelationManager;
use App\Models\Memorizer;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\ActionsPosition;
use Filament\Tables\Table;

class MemorizerResource extends Resource
{
    protected static ?string $model = Memorizer::class;

    protected static ?string $navigationIcon = 'heroicon-o-user';

    protected static ?string $navigationLabel = 'الطلاب';

    protected static ?string $modelLabel = 'طالب';

    protected static ?string $pluralModelLabel = 'الطلاب';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make()
                    ->schema([
                        TextInput::make('name')
                            ->label('الإسم')
                            ->required(),
                        TextInput::make('phone')
                            ->label('الهاتف')
                            ->required(),
                        Select::make('memo_group_id')
                            ->label('المجموعة')
                            ->hiddenOn(MemorizersRelationManager::class)
                            ->relationship('group', 'name')
                            ->required(),
                        ToggleButtons::make('sex')
                            ->inline()
                            ->options([
                                'male' => 'ذكر',
                                'female' => 'أنثى',
                            ])->default('male')
                            ->label('الجنس')
                            ->required(),
                        TextInput::make('city')
                            ->label('المدينة')
                            ->default('أسفي'),
                        Toggle::make('exempt')
                            ->label('معفى من الدفع')
                            ->default(false),
                    ])->columns()
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->color(fn(Memorizer $record) => $record->hasPaymentThisMonth() ? 'success' : 'default')
                    ->weight(fn(Memorizer $record) => $record->hasPaymentThisMonth() ? 'bold' : 'normal')
                    ->label('الإسم'),
                TextColumn::make('phone')
                    ->label('الهاتف'),
                TextColumn::make('sex')
                    ->getStateUsing(fn(Memorizer $record) => match ($record->sex) {
                        'male' => 'ذكر',
                        'female' => 'أنثى'
                    })
                    ->label('الجنس'),
                TextColumn::make('city')
                    ->label('المدينة'),
                TextColumn::make('group.name')
                    ->label('المجموعة'),
                TextColumn::make('exempt')
                    ->label('معفي')
            ])
            ->filters([
                //
            ])

            ->actions([
                Tables\Actions\EditAction::make()->slideOver(),
                Action::make('pay_this_month')
                    ->label('دفع الشهر')
                    ->requiresConfirmation()
                    ->hidden(fn(Memorizer $record) =>  $record->hasPaymentThisMonth())
                    ->modalDescription('هل أنت متأكد من دفع الشهر؟')
                    ->modalHeading('دفع الشهر')
                    ->action(function (Memorizer $record) {
                        $record->payments()->create([
                            'amount' => 100,
                            'payment_date' => now(),
                        ]);

                        Notification::make()
                            ->title('تم الدفع بنجاح')
                            ->success()
                            ->send();
                    }),
            ], ActionsPosition::BeforeColumns)
            ->bulkActions([
                Tables\Actions\BulkAction::make('pay_this_month')
                    ->label('دفع الشهر')
                    ->requiresConfirmation()
                    ->modalDescription('هل أنت متأكد من دفع الشهر للطلاب المحددين؟')
                    ->modalHeading('دفع الشهر')
                    ->action(function ($livewire) {
                        $records = $livewire->getSelectedTableRecords();
                        $records = Memorizer::find($records);
                        foreach ($records as $record) {
                            $record->payments()->create([
                                'amount' => 100,
                                'payment_date' => now(),
                            ]);
                        }

                        Notification::make()
                            ->title('تم الدفع بنجاح')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            PaymentsRelationManager::class
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMemorizers::route('/'),
            'create' => Pages\CreateMemorizer::route('/create'),
            'edit' => Pages\EditMemorizer::route('/{record}/edit'),
        ];
    }
}
