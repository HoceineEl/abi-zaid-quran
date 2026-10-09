<?php

namespace App\Filament\Association\Resources\GroupResource\RelationManagers;

use App\Filament\Association\Resources\MemorizerResource;
use App\Models\Memorizer;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ManageStudentsRelationManager extends RelationManager
{
    protected static string $relationship = 'memorizers';

    protected static bool $isLazy = false;

    protected static ?string $title = 'طلبة الحلقة';

    protected static ?string $modelLabel = 'طالب';

    protected static ?string $pluralModelLabel = 'الطلبة';

    protected static ?string $icon = 'heroicon-o-academic-cap';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Form $form): Form
    {
        return $form->schema(MemorizerResource::groupStudentFormSchema())->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label('الطالب')
                    ->weight(FontWeight::Bold)
                    ->searchable()
                    ->sortable()
                    ->description(fn (Memorizer $record): ?string => $record->display_phone),
                TextColumn::make('age')
                    ->label('العمر')
                    ->badge()
                    ->color('gray')
                    ->suffix(' سنة')
                    ->placeholder('—')
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('birth_date', $direction === 'asc' ? 'desc' : 'asc')),
                TextColumn::make('school_level')
                    ->label('المستوى الدراسي')
                    ->badge()
                    ->color('info')
                    ->placeholder('غير محدد')
                    ->sortable(),
                TextColumn::make('memorized_hizbs')
                    ->label('المحفوظ')
                    ->suffix(' حزب')
                    ->placeholder('—')
                    ->sortable(),
                IconColumn::make('attended_summer_session')
                    ->label('الدورة الصيفية')
                    ->boolean()
                    ->alignCenter(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('إضافة طالب جديد')
                    ->icon('heroicon-o-user-plus')
                    ->modalHeading('إضافة طالب جديد إلى الحلقة')
                    ->slideOver()
                    ->createAnother(true)
                    ->successNotificationTitle('تمت إضافة الطالب'),
                $this->pickFromPoolAction(),
            ])
            ->actions([
                EditAction::make()
                    ->label('تعديل')
                    ->slideOver(),
                ActionGroup::make([
                    Action::make('remove_from_group')
                        ->label('إخراج من الحلقة')
                        ->icon('heroicon-o-arrow-right-start-on-rectangle')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading(fn (Memorizer $record): string => 'إخراج '.$record->name.' من الحلقة')
                        ->modalDescription('يبقى الطالب مسجلا بأداءاته وغياباته في لائحة «بدون مجموعة»، ويمكن إضافته لاحقا إلى حلقة أخرى.')
                        ->modalSubmitActionLabel('إخراج')
                        ->action(function (Memorizer $record): void {
                            $record->update(['memo_group_id' => null]);

                            Notification::make()->title('تم إخراج الطالب من الحلقة')->success()->send();
                        }),
                    DeleteAction::make()
                        ->label('حذف نهائي')
                        ->modalHeading(fn (Memorizer $record): string => 'حذف '.$record->name.' نهائيا')
                        ->modalDescription('سيُحذف الطالب مع كل أداءاته وغياباته، ولا يمكن التراجع عن ذلك.'),
                ]),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('remove_from_group')
                        ->label('إخراج المحددين من الحلقة')
                        ->icon('heroicon-o-arrow-right-start-on-rectangle')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalDescription('يبقى الطلبة مسجلين بأداءاتهم في لائحة «بدون مجموعة».')
                        ->action(fn (Collection $records) => Memorizer::whereKey($records->modelKeys())->update(['memo_group_id' => null]))
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make()
                        ->label('حذف المحددين نهائيا'),
                ]),
            ])
            ->emptyStateIcon('heroicon-o-user-group')
            ->emptyStateHeading('لا يوجد طلبة في هذه الحلقة بعد')
            ->emptyStateDescription('أضف طالبا جديدا، أو اختر طلبة مسجلين من لائحة «بدون مجموعة».')
            ->emptyStateActions([
                CreateAction::make('create_first')
                    ->label('إضافة طالب جديد')
                    ->icon('heroicon-o-user-plus')
                    ->slideOver(),
            ])
            ->paginated(false);
    }

    protected function pickFromPoolAction(): Action
    {
        return Action::make('pick_from_pool')
            ->label('إضافة طالب مسجل')
            ->icon('heroicon-o-user-group')
            ->color('gray')
            ->modalHeading('إضافة طلبة مسجلين إلى الحلقة')
            ->modalDescription('ابحث عن طلبة مسجلين في الجمعية وليسوا في أي حلقة، ثم أضفهم إلى هذه الحلقة.')
            ->modalSubmitActionLabel('إضافة إلى الحلقة')
            ->form([
                Select::make('memorizer_ids')
                    ->label('الطلبة')
                    ->multiple()
                    ->searchable()
                    ->required()
                    ->options(fn (): array => Memorizer::withoutGroup()
                        ->orderBy('name')
                        ->get()
                        ->mapWithKeys(fn (Memorizer $memorizer): array => [$memorizer->id => $this->poolOptionLabel($memorizer)])
                        ->all())
                    ->searchPrompt('اكتب اسم الطالب للبحث')
                    ->noSearchResultsMessage('لا يوجد طالب بهذا الاسم في لائحة «بدون مجموعة».')
                    ->helperText(fn (): string => 'عدد الطلبة بدون مجموعة: '.Memorizer::withoutGroup()->count()),
            ])
            ->action(function (array $data): void {
                $count = Memorizer::withoutGroup()
                    ->whereKey($data['memorizer_ids'])
                    ->update(['memo_group_id' => $this->getOwnerRecord()->getKey()]);

                Notification::make()
                    ->title("تمت إضافة {$count} طالب إلى الحلقة")
                    ->success()
                    ->send();
            });
    }

    protected function poolOptionLabel(Memorizer $memorizer): string
    {
        return collect([
            $memorizer->name,
            $memorizer->age ? $memorizer->age.' سنة' : null,
            $memorizer->display_phone,
        ])->filter()->join(' · ');
    }
}
