<?php

namespace App\Filament\Resources\StudentDisconnectionResource\Pages;

use App\Filament\Exports\StudentDisconnectionExporter;
use App\Exports\StudentDisconnectionExport;
use App\Filament\Resources\StudentDisconnectionResource;
use App\Models\Group;
use App\Models\Student;
use App\Models\StudentDisconnection;
use App\Services\DisconnectionService;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Notifications\Notification;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListStudentDisconnections extends ListRecords
{
    protected static string $resource = StudentDisconnectionResource::class;

    protected DisconnectionService $disconnectionService;

    public function boot(): void
    {
        $this->disconnectionService = app(DisconnectionService::class);
    }

    public function getTabs(): array
    {
        $fourteenDaysAgo = now()->subDays(14)->format('Y-m-d');
        $today = now()->format('Y-m-d');


        return [
            'all' => Tab::make('الكل'),
            'not_returned' => Tab::make('لم يعودوا')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('has_returned', false)),
            'returned' => Tab::make('عادوا')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('has_returned', true)),
        ];
    }


    public function getDefaultActiveTab(): string|int|null
    {
        return 'not_returned';
    }

    protected function getTableQuery(): Builder
    {
        return parent::getTableQuery()
            ->with([
                'student.progresses' => function ($query) {
                    $query->where('status', 'memorized')->latest('date')->limit(1);
                },
                'group'
            ])
            ->orderByRaw('
                (SELECT MAX(p.date)
                 FROM progress p
                 WHERE p.student_id = student_disconnections.student_id
                 AND p.status = "memorized") DESC
            ');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('get_disconnected_students')
                ->label('إضافة الطلاب المنقطعين')
                ->icon('heroicon-o-plus-circle')
                ->color('success')
                ->modalWidth('5xl')
                ->modalHeading('إضافة الطلاب المنقطعين')
                ->modalDescription('سيتم إضافة الطلاب من المجموعات النشطة (لديها تقدم في آخر 7 أيام) الذين لديهم ثلاثة أيام أو أكثر غياب متتالية إلى قائمة الانقطاع.')
                ->form([
                    \Filament\Forms\Components\Select::make('excluded_groups')
                        ->label('استثناء المجموعات')
                        ->multiple()
                        ->options(Group::withoutGlobalScope('userGroups')->active()->pluck('name', 'id'))
                        ->searchable()
                        ->preload()
                        ->live()
                        ->helperText('اختر المجموعات النشطة التي لا تريد إضافة طلابها إلى قائمة الانقطاع (المجموعات النشطة = لديها تقدم في آخر 7 أيام)')
                        ->hintActions([
                            \Filament\Forms\Components\Actions\Action::make('select_all')
                                ->label('اختيار الكل')
                                ->icon('heroicon-m-check-circle')
                                ->action(function ($set, $get) {
                                    $allGroupIds = Group::withoutGlobalScope('userGroups')->active()->pluck('id')->toArray();
                                    $set('excluded_groups', $allGroupIds);
                                }),
                            \Filament\Forms\Components\Actions\Action::make('select_female_groups')
                                ->label('اختيار مجموعات الإناث')
                                ->icon('heroicon-m-user-group')
                                ->action(function ($set, $get) {
                                    $femaleGroupIds = Group::withoutGlobalScope('userGroups')
                                        ->active()
                                        ->where(function ($query) {
                                            $query->where('name', 'like', '%الحافظات%')
                                                ->orWhere('name', 'like', '%نساء%')
                                                ->orWhere('name', 'like', '%حافظات%');
                                        })
                                        ->pluck('id')
                                        ->toArray();
                                    $set('excluded_groups', $femaleGroupIds);
                                }),
                            \Filament\Forms\Components\Actions\Action::make('clear_all')
                                ->label('إلغاء الكل')
                                ->icon('heroicon-m-x-circle')
                                ->color('danger')
                                ->action(function ($set) {
                                    $set('excluded_groups', []);
                                }),
                        ]),
                ])
                ->action(function ($data) {
                    $this->addDisconnectedStudents($data['excluded_groups'] ?? []);
                }),
            Actions\Action::make('check_returned_students')
                ->label('فحص الطلاب العائدين')
                ->icon('heroicon-o-arrow-path')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('فحص الطلاب العائدين')
                ->modalDescription('سيتم فحص الطلاب المنقطعين للتحقق من عودتهم بناءً على الحضور الحديث.')
                ->action(function () {
                    $this->checkReturnedStudents();
                }),
            Actions\Action::make('export_table')
                ->label('تصدير كشف الانقطاع')
                ->icon('heroicon-o-share')
                ->color('success')
                ->action(function () {
                    $disconnections = StudentDisconnection::with(['student', 'group'])
                        ->orderBy('created_at', 'desc')
                        ->get();

                    $html = view('components.student-disconnections-export-table', [
                        'disconnections' => $disconnections,
                    ])->render();

                    $this->dispatch('export-table', [
                        'html' => $html,
                        'title' => 'كشف الطلاب المنقطعين',
                        'dateRange' => 'جميع الطلاب المنقطعين'
                    ]);
                }),
            // Actions\ExportAction::make()
            //     ->label('تصدير Excel')
            //     ->exporter(StudentDisconnectionExporter::class)
            //     ->icon('heroicon-o-arrow-down-tray'),
            Actions\Action::make('export_custom_excel')
                ->label('تصدير Excel')
                ->icon('heroicon-o-document-arrow-down')
                ->color('warning')
                ->form([
                    \Filament\Forms\Components\DatePicker::make('start_date')
                        ->label('من تاريخ آخر حضور')
                        ->displayFormat('m/d/Y')
                        ->default(now()->subDays(14)->format('Y-m-d')),
                    \Filament\Forms\Components\DatePicker::make('end_date')
                        ->label('إلى تاريخ آخر حضور')
                        ->displayFormat('m/d/Y')
                        ->default(now()->format('Y-m-d')),
                    \Filament\Forms\Components\Toggle::make('include_returned')
                        ->label('تضمين الطلاب العائدين')
                        ->default(true)
                        ->helperText('اختر ما إذا كنت تريد تضمين الطلاب الذين عادوا في التصدير'),
                ])
                ->action(function (array $data) {
                    $startDate = $data['start_date'];
                    $endDate = $data['end_date'];
                    $includeReturned = $data['include_returned'] ?? true;
                    $dateRange = "من {$startDate} إلى {$endDate}";

                    $export = new StudentDisconnectionExport($dateRange, $startDate, $endDate, $includeReturned);
                    return \Maatwebsite\Excel\Facades\Excel::download($export, 'students-disconnection-' . now()->format('Y-m-d') . '.xlsx');
                }),
        ];
    }

    private function addDisconnectedStudents(array $excludedGroups = []): void
    {
        $result = $this->disconnectionService->addDisconnectedStudents($excludedGroups);
        $addedCount = $result['added'];
        $skippedStudents = $result['skipped'];

        // Build notification body
        $body = '';

        if ($addedCount > 0) {
            $body .= "تم إضافة {$addedCount} طالب إلى قائمة الانقطاع.";
        }

        if ($skippedStudents->count() > 0) {
            $skippedNames = $skippedStudents->pluck('name')->take(5)->join('، ');
            $remainingCount = $skippedStudents->count() - 5;

            if ($addedCount > 0) {
                $body .= "\n\n";
            }

            $body .= "تم تخطي {$skippedStudents->count()} طالب (موجودين بالفعل في القائمة ولم يعودوا بعد):\n{$skippedNames}";

            if ($remainingCount > 0) {
                $body .= " و {$remainingCount} آخرين";
            }
        }

        if ($addedCount > 0) {
            Notification::make()
                ->title('تم إضافة الطلاب المنقطعين')
                ->body($body)
                ->success()
                ->duration(10000) // Show for 10 seconds to allow reading the list
                ->send();
        } elseif ($skippedStudents->count() > 0) {
            Notification::make()
                ->title('لا يوجد طلاب جدد لإضافتهم')
                ->body($body)
                ->warning()
                ->duration(10000)
                ->send();
        } else {
            Notification::make()
                ->title('لا يوجد طلاب منقطعين')
                ->body('لا يوجد طلاب لديهم ثلاثة أيام أو أكثر غياب متتالية في المجموعات النشطة.')
                ->info()
                ->send();
        }
    }

    private function checkReturnedStudents(): void
    {
        $returnedCount = $this->disconnectionService->checkReturnedStudents();

        if ($returnedCount > 0) {
            Notification::make()
                ->title('تم تحديث حالة الطلاب العائدين')
                ->body("تم تحديث حالة {$returnedCount} طالب كعائد.")
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('لا يوجد طلاب عائدين جدد')
                ->body('لم يتم العثور على طلاب عائدين جدد بناءً على سجلات الحضور.')
                ->info()
                ->send();
        }
    }
}
