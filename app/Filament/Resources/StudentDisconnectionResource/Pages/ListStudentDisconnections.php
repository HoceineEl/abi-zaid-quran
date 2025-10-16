<?php

namespace App\Filament\Resources\StudentDisconnectionResource\Pages;

use App\Exports\StudentDisconnectionExport;
use App\Filament\Exports\StudentDisconnectionExporter;
use App\Filament\Resources\StudentDisconnectionResource;
use App\Models\Group;
use App\Models\StudentDisconnection;
use App\Services\DisconnectionService;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Facades\Excel;

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
                ->modifyQueryUsing(fn (Builder $query) => $query->where('has_returned', false)),
            'returned' => Tab::make('عادوا')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('has_returned', true)),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'not_returned';
    }

    protected function getTableQuery(): Builder
    {
        return parent::getTableQuery()
            ->with(['student.progresses' => function ($query) {
                $query->where('status', 'memorized')->latest('date')->limit(1);
            }])
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
            CreateAction::make(),
            Action::make('get_disconnected_students')
                ->label('إضافة الطلاب المنقطعين')
                ->icon('heroicon-o-plus-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('إضافة الطلاب المنقطعين')
                ->modalDescription('سيتم إضافة الطلاب من المجموعات النشطة (لديها تقدم في آخر 7 أيام) الذين لديهم يومان أو أكثر غياب متتاليان إلى قائمة الانقطاع.')
                ->schema([
                    Select::make('excluded_groups')
                        ->label('استثناء المجموعات')
                        ->multiple()
                        ->options(Group::active()->pluck('name', 'id'))
                        ->searchable()
                        ->preload()
                        ->helperText('اختر المجموعات النشطة التي لا تريد إضافة طلابها إلى قائمة الانقطاع (المجموعات النشطة = لديها تقدم في آخر 7 أيام)'),
                ])
                ->action(function ($data) {
                    $this->addDisconnectedStudents($data['excluded_groups'] ?? []);
                }),
            Action::make('check_returned_students')
                ->label('فحص الطلاب العائدين')
                ->icon('heroicon-o-arrow-path')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('فحص الطلاب العائدين')
                ->modalDescription('سيتم فحص الطلاب المنقطعين للتحقق من عودتهم بناءً على الحضور الحديث.')
                ->action(function () {
                    $this->checkReturnedStudents();
                }),
            Action::make('export_table')
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
                        'dateRange' => 'جميع الطلاب المنقطعين',
                    ]);
                }),
            // Actions\ExportAction::make()
            //     ->label('تصدير Excel')
            //     ->exporter(StudentDisconnectionExporter::class)
            //     ->icon('heroicon-o-arrow-down-tray'),
            Action::make('export_custom_excel')
                ->label('تصدير Excel')
                ->icon('heroicon-o-document-arrow-down')
                ->color('warning')
                ->schema([
                    DatePicker::make('start_date')
                        ->label('من تاريخ آخر حضور')
                        ->displayFormat('m/d/Y')
                        ->default(now()->subDays(14)->format('Y-m-d')),
                    DatePicker::make('end_date')
                        ->label('إلى تاريخ آخر حضور')
                        ->displayFormat('m/d/Y')
                        ->default(now()->format('Y-m-d')),
                    Toggle::make('include_returned')
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

                    return Excel::download($export, 'students-disconnection-'.now()->format('Y-m-d').'.xlsx');
                }),
        ];
    }

    private function addDisconnectedStudents(array $excludedGroups = []): void
    {
        $addedCount = $this->disconnectionService->addDisconnectedStudents($excludedGroups);

        if ($addedCount > 0) {
            Notification::make()
                ->title('تم إضافة الطلاب المنقطعين')
                ->body("تم إضافة {$addedCount} طالب إلى قائمة الانقطاع من المجموعات النشطة (لديها تقدم في آخر 7 أيام).")
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('لا يوجد طلاب منقطعين')
                ->body('لا يوجد طلاب لديهم يومان أو أكثر غياب متتاليان في المجموعات النشطة (لديها تقدم في آخر 7 أيام) أو تم إضافتهم مسبقاً.')
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
