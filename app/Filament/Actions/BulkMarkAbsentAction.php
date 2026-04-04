<?php

namespace App\Filament\Actions;

use App\Models\Group;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Wizard\Step;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables\Actions\BulkAction;
use Illuminate\Support\HtmlString;

class BulkMarkAbsentAction extends BulkAction
{
    public static function getDefaultName(): ?string
    {
        return 'bulk_mark_absent';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('تسجيل الغائبين')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->modalWidth(MaxWidth::Large)
            ->modalHeading('تسجيل الغائبين جماعياً')
            ->modalSubmitActionLabel('تسجيل الغائبين')
            ->visible(fn (): bool => auth()->user()->isAdministrator())
            ->steps([
                Step::make('date')
                    ->label('التاريخ')
                    ->icon('heroicon-o-calendar')
                    ->schema([
                        DatePicker::make('date')
                            ->label('تاريخ الحضور')
                            ->default(today())
                            ->required()
                            ->native(false)
                            ->displayFormat('Y-m-d'),
                        Hidden::make('groups_summary'),
                    ])
                    ->afterValidation(function (Get $get, Set $set): void {
                        $date = $get('date') ?? today()->format('Y-m-d');
                        $groupIds = $this->getLivewire()->selectedTableRecords ?? [];

                        $summary = Group::with([
                            'students.progresses' => fn ($q) => $q
                                ->whereDate('date', $date)
                                ->select(['id', 'student_id', 'date', 'status']),
                        ])->whereKey($groupIds)->get()->map(fn (Group $group) => [
                            'name'  => $group->name,
                            'count' => $group->students->filter(fn ($s) => $s->progresses->isEmpty())->count(),
                        ])->filter(fn ($g) => $g['count'] > 0)->values()->all();

                        $set('groups_summary', json_encode($summary, JSON_UNESCAPED_UNICODE));
                    }),

                Step::make('confirm')
                    ->label('التأكيد')
                    ->icon('heroicon-o-check-circle')
                    ->schema([
                        Placeholder::make('summary_display')
                            ->label('')
                            ->content(function (Get $get): HtmlString {
                                $groups = json_decode($get('groups_summary') ?? '[]', true) ?? [];
                                $total  = array_sum(array_column($groups, 'count'));
                                $date   = $get('date') ?? today()->format('Y-m-d');

                                if (empty($groups)) {
                                    return new HtmlString('<p class="text-sm text-gray-500">لا يوجد طلاب غائبون غير مسجلين في المجموعات المحددة.</p>');
                                }

                                $rows = collect($groups)->map(fn ($g) => "
                                    <div class='flex items-center justify-between rounded-xl bg-gray-50 px-3 py-2 dark:bg-gray-800/60'>
                                        <span class='text-sm text-gray-700 dark:text-gray-200'>{$g['name']}</span>
                                        <span class='rounded-full bg-danger-50 px-2 py-0.5 text-xs font-bold text-danger-700 dark:bg-danger-500/10 dark:text-danger-400'>{$g['count']}</span>
                                    </div>")->implode('');

                                return new HtmlString("
                                    <div class='space-y-2' dir='rtl'>
                                        <p class='text-sm font-medium text-gray-700 dark:text-gray-200'>
                                            سيتم تسجيل <strong class='text-danger-600'>{$total}</strong> طالب كغائب في تاريخ {$date}:
                                        </p>
                                        <div class='space-y-1.5 mt-2'>{$rows}</div>
                                    </div>");
                            }),
                    ]),
            ])
            ->action(function (array $data, $records): void {
                $date  = $data['date'];
                $count = 0;

                foreach ($records as $group) {
                    $group->load([
                        'students.progresses' => fn ($q) => $q
                            ->whereDate('date', $date)
                            ->select(['id', 'student_id', 'date', 'status']),
                    ]);

                    foreach ($group->students as $student) {
                        $progress = $student->progresses->first();

                        if ($progress?->status === 'memorized') {
                            continue;
                        }

                        if ($progress) {
                            $progress->update(['status' => 'absent', 'with_reason' => false, 'comment' => null]);
                        } else {
                            $student->progresses()->create([
                                'date'       => $date,
                                'status'     => 'absent',
                                'with_reason' => false,
                                'comment'    => null,
                                'page_id'    => null,
                                'lines_from' => null,
                                'lines_to'   => null,
                            ]);
                        }

                        $count++;
                    }
                }

                Notification::make()
                    ->success()
                    ->title("تم تسجيل {$count} طالب كغائب")
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }
}
