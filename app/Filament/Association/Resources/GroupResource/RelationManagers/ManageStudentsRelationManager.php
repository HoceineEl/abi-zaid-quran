<?php

namespace App\Filament\Association\Resources\GroupResource\RelationManagers;

use App\Filament\Association\Resources\MemorizerResource;
use App\Models\Memorizer;
use Carbon\Carbon;
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
                    ->color('gray')
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
                $this->pickFromPoolAction()->name('pick_first_from_pool'),
                CreateAction::make('create_first')
                    ->label('إضافة طالب جديد')
                    ->icon('heroicon-o-user-plus')
                    ->color('gray')
                    ->slideOver(),
            ])
            ->paginated(false);
    }

    protected function pickFromPoolAction(): Action
    {
        return Action::make('pick_from_pool')
            ->label('إضافة طلبة مسجلين')
            ->icon('heroicon-o-magnifying-glass-plus')
            ->modalIcon('heroicon-o-user-group')
            ->modalWidth('2xl')
            ->modalHeading('إضافة طلبة مسجلين إلى الحلقة')
            ->modalDescription('ابحث عن طلبتك بالاسم أو رقم الهاتف أو اسم ولي الأمر أو تاريخ الازدياد، واختر منهم من شئت دفعة واحدة.')
            ->modalSubmitActionLabel('إضافة إلى الحلقة')
            ->form([
                Select::make('memorizer_ids')
                    ->label('الطلبة')
                    ->multiple()
                    ->searchable()
                    ->required()
                    ->allowHtml()
                    ->optionsLimit(30)
                    ->searchDebounce(300)
                    ->getSearchResultsUsing(fn (string $search): array => $this->poolOptions($this->poolQuery()
                        ->matching($search)
                        ->with('guardian')
                        ->orderBy('name')
                        ->limit(30)
                        ->get()))
                    ->getOptionLabelsUsing(fn (array $values): array => $this->poolOptions(Memorizer::with('guardian')
                        ->whereKey($values)
                        ->get()))
                    ->searchPrompt('اكتب الاسم أو الهاتف (مثل 0661…) أو سنة الازدياد')
                    ->searchingMessage('جارٍ البحث…')
                    ->noSearchResultsMessage('لا يوجد طالب مطابق في لائحة «بدون مجموعة».')
                    ->helperText(fn (): string => 'الطلبة المتاحون لهذه الحلقة: '.$this->poolQuery()->count()),
            ])
            ->action(function (array $data): void {
                $count = $this->poolQuery()
                    ->whereKey($data['memorizer_ids'])
                    ->update(['memo_group_id' => $this->getOwnerRecord()->getKey()]);

                Notification::make()
                    ->title("تمت إضافة {$count} طالب إلى الحلقة")
                    ->success()
                    ->send();
            });
    }

    protected function poolQuery(): Builder
    {
        $sex = $this->getOwnerRecord()->teacher?->sex;

        return Memorizer::withoutGroup()
            ->when($sex, fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('sex', $sex)
                ->orWhereNull('sex')));
    }

    /**
     * @param  Collection<int, Memorizer>  $memorizers
     * @return array<int, string>
     */
    protected function poolOptions(Collection $memorizers): array
    {
        return $memorizers
            ->mapWithKeys(fn (Memorizer $memorizer): array => [$memorizer->id => $this->poolOptionLabel($memorizer)])
            ->all();
    }

    protected function poolOptionLabel(Memorizer $memorizer): string
    {
        $details = collect([
            $memorizer->display_phone ? '📞 <span dir="ltr">'.e($memorizer->display_phone).'</span>' : null,
            $memorizer->birth_date ? '🎂 '.e(Carbon::parse($memorizer->birth_date)->format('d/m/Y')).' ('.$memorizer->age.' سنة)' : null,
            $memorizer->school_level ? e($memorizer->school_level->getLabel()) : null,
            $memorizer->memorized_hizbs !== null ? e($memorizer->memorized_hizbs).' حزب' : null,
            $memorizer->guardian?->name ? 'ولي الأمر: '.e($memorizer->guardian->name) : null,
        ])->filter()->join(' · ');

        $needsReview = str_contains((string) $memorizer->notes, 'يُرجى')
            ? ' <span style="color:#b45309;font-size:.75rem">● يحتاج مراجعة</span>'
            : '';

        return '<div style="line-height:1.4"><div style="font-weight:700">'.e($memorizer->name).$needsReview.'</div>'
            .'<div style="font-size:.8rem;color:#6b7280">'.($details ?: 'لا توجد معلومات إضافية').'</div></div>';
    }
}
