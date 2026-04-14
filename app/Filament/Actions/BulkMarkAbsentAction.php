<?php

namespace App\Filament\Actions;

use Filament\Actions\BulkAction;
use Filament\Support\Enums\Width;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use App\Models\Group;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
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
            ->modalWidth(Width::Large)
            ->modalHeading('تسجيل الغائبين جماعياً')
            ->modalSubmitActionLabel('تسجيل الغائبين')
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

                                return new HtmlString(
                                    view('filament.actions.bulk-mark-absent-modal', compact('groups', 'total', 'date'))->render()
                                );
                            }),
                    ]),
            ])
            ->action(function (array $data, $records): void {
                $date  = $data['date'];
                $count = 0;

                $records->load([
                    'students.progresses' => fn ($q) => $q
                        ->whereDate('date', $date)
                        ->select(['id', 'student_id', 'date', 'status']),
                ]);

                foreach ($records as $group) {
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
