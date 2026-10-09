<?php

namespace App\Filament\Association\Resources\GroupResource\Pages;

use App\Filament\Association\Resources\GroupResource;
use App\Models\MemoGroup;
use App\Models\Memorizer;
use Filament\Actions;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

class ListGroups extends ListRecords
{
    protected static string $resource = GroupResource::class;

    public function getTabs(): array
    {
        if (! auth()->user()->isTeacher()) {
            return [
                'males' => Tab::make('الذكور')
                    ->query(fn (Builder $query) => $query->whereHas('teacher', fn (Builder $query) => $query->where('sex', 'male'))),
                'females' => Tab::make('الإناث')
                    ->query(fn (Builder $query) => $query->whereHas('teacher', fn (Builder $query) => $query->where('sex', 'female'))),
            ];
        }

        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->slideOver()
                ->hidden(fn () => auth()->user()->isTeacher()),
            $this->startNewSchoolYearAction(),
        ];
    }

    protected function startNewSchoolYearAction(): Actions\Action
    {
        $confirmationWord = 'تأكيد';

        return Actions\Action::make('start_new_school_year')
            ->label('بدء سنة دراسية جديدة')
            ->icon('heroicon-o-arrow-path')
            ->color('danger')
            ->outlined()
            ->visible(fn (): bool => auth()->user()->hasAssociationAccess())
            ->modalIcon('heroicon-o-exclamation-triangle')
            ->modalHeading('بدء سنة دراسية جديدة')
            ->modalDescription('تُحذف كل المجموعات لتُنشأ من جديد. الأساتذة وأولياء الأمور يبقون كما هم، والطلبة ينتقلون إلى لائحة «بدون مجموعة» مع أداءاتهم وغياباتهم، ثم يضيفهم الأساتذة إلى حلقاتهم الجديدة.')
            ->modalSubmitActionLabel('حذف المجموعات وبدء السنة')
            ->form([
                Placeholder::make('summary')
                    ->hiddenLabel()
                    ->content(fn (): HtmlString => new HtmlString(sprintf(
                        '<ul class="list-disc ps-5 space-y-1 text-sm"><li>المجموعات التي ستُحذف: <b>%d</b></li><li>الطلبة الذين سينتقلون إلى «بدون مجموعة»: <b>%d</b></li><li>الأساتذة وأولياء الأمور والأداءات لا تُحذف.</li></ul>',
                        MemoGroup::count(),
                        Memorizer::whereNotNull('memo_group_id')->count(),
                    ))),
                TextInput::make('confirmation')
                    ->label("اكتب «{$confirmationWord}» للمتابعة")
                    ->required()
                    ->in([$confirmationWord])
                    ->validationMessages(['in' => "اكتب «{$confirmationWord}» كما هي."]),
            ])
            ->action(function (): void {
                $deletedGroups = DB::transaction(fn (): int => MemoGroup::query()->delete());

                Notification::make()
                    ->title('بدأت السنة الدراسية الجديدة')
                    ->body("حُذفت {$deletedGroups} مجموعة. أنشئ المجموعات الجديدة واربط كل واحدة بأستاذها.")
                    ->success()
                    ->persistent()
                    ->send();
            });
    }
}
