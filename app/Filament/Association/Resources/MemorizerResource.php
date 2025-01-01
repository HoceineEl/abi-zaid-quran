<?php

namespace App\Filament\Association\Resources;

use App\Filament\Association\Resources\GroupResource\RelationManagers\MemorizersRelationManager;
use App\Filament\Association\Resources\MemorizerResource\Pages;
use App\Filament\Association\Resources\MemorizerResource\RelationManagers\PaymentsRelationManager;
use App\Filament\Exports\MemorizerExporter;
use App\Filament\Imports\MemorizerImporter;
use App\Models\Memorizer;
use Filament\Actions\Action as ActionsAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\ExportAction;
use Filament\Tables\Actions\ExportBulkAction;
use Filament\Tables\Actions\ImportAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\ActionsPosition;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\File;
use Livewire\Component;

use function GuzzleHttp\default_ca_bundle;

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
                        Select::make('teacher_id')
                            ->label('المعلم')
                            ->relationship('teacher', 'name')
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
                        FileUpload::make('photo')
                            ->image()
                            ->avatar()
                            ->directory('memorizers-photos')
                            ->maxSize(1024)
                            ->maxFiles(1)
                            ->imageEditor()
                            ->label('الصورة'),
                        Toggle::make('exempt')
                            ->label('معفى من الدفع')
                            ->default(false),
                    ])->columns(),
            ]);
    }

    public static function table(Table $table): Table
    {

        return $table
            ->columns([
                ImageColumn::make('photo')
                    ->label('الصورة')
                    ->circular()
                    ->searchable()
                    ->toggleable()
                    ->sortable()
                    ->size(50),
                TextColumn::make('name')
                    ->color(fn(Memorizer $record) => $record->hasPaymentThisMonth() ? 'success' : 'default')
                    ->searchable()
                    ->toggleable()
                    ->sortable()
                    ->weight(fn(Memorizer $record) => $record->hasPaymentThisMonth() ? 'bold' : 'normal')
                    ->label('الإسم'),
                TextColumn::make('phone')
                    ->searchable()
                    ->toggleable()
                    ->sortable()
                    ->label('الهاتف'),
                TextColumn::make('sex')
                    ->getStateUsing(fn(Memorizer $record) => match ($record->sex) {
                        'male' => 'ذكر',
                        'female' => 'أنثى',
                        default => 'ذكر',
                    })
                    ->searchable()
                    ->toggleable()
                    ->sortable()
                    ->label('الجنس'),
                TextColumn::make('city')
                    ->searchable()
                    ->toggleable()
                    ->sortable()
                    ->label('المدينة'),
                TextColumn::make('group.name')
                    ->searchable()
                    ->toggleable()
                    ->sortable()
                    ->label('المجموعة'),
                TextColumn::make('teacher.name')
                    ->searchable()
                    ->toggleable()
                    ->sortable()
                    ->label('المعلم'),

                IconColumn::make('exempt')
                    ->searchable()
                    ->toggleable()
                    ->sortable()
                    ->label('معفي')
                    ->boolean(),
            ])
            ->filters([
                //
            ])
            ->headerActions([

                ExportAction::make()
                    ->label('تصدير البيانات')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->exporter(MemorizerExporter::class),

                ImportAction::make()
                    ->label('استيراد البيانات')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->importer(MemorizerImporter::class),
            ])
            ->actions([
                ActionGroup::make([
                    Tables\Actions\EditAction::make()->slideOver(),

                ]),
                Action::make('pay_this_month')
                    ->label('دفع')
                    ->icon('heroicon-o-currency-dollar')
                    ->color('success')
                    ->requiresConfirmation()
                    ->hidden(fn(Memorizer $record) => $record->hasPaymentThisMonth())
                    ->modalDescription('هل تريد تسجيل دفعة جديدة لهذا الشهر؟')
                    ->modalHeading('تسجيل دفعة جديدة')
                    ->action(function (Memorizer $record) {
                        $record->payments()->create([
                            'amount' => 100,
                            'payment_date' => now(),
                        ]);

                        Notification::make()
                            ->title('تم تسجيل الدفعة بنجاح')
                            ->success()
                            ->send();
                    }),

            ], ActionsPosition::BeforeColumns)
            ->bulkActions([
                ExportBulkAction::make()
                    ->label('تصدير البيانات')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->exporter(MemorizerExporter::class),
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
            ])->filters([
                Tables\Filters\SelectFilter::make('memo_group_id')
                    ->label('المجموعة')
                    ->relationship('group', 'name')
                    ->multiple()
                    ->preload(),

                Tables\Filters\SelectFilter::make('teacher_id')
                    ->label('المعلم')
                    ->relationship('teacher', 'name')
                    ->multiple()
                    ->preload(),



            ]);
    }
    protected function getHeaderActions(): array
    {
        FilamentAsset::register([
            Js::make('https://html2canvas.hertzen.com/dist/html2canvas.min.js'),
        ]);

        return [
            ActionsAction::make('snapshot')
                ->label('Take Snapshot')
                ->icon('heroicon-o-camera')
                ->action(function (Component $livewire) {
                    // This will be called when the action is triggered
                    $livewire->dispatch('takeSnapshot');
                })
                ->after(function () {
                    Notification::make()
                        ->title('Snapshot taken')
                        ->success()
                        ->send();
                })
        ];
    }
    public static function getRelations(): array
    {
        return [
            PaymentsRelationManager::class,
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
