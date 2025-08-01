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
        $stats = $this->disconnectionService->getDisconnectionStats();

        return [
            'all' => Tab::make('الكل')
                ->badge($stats['total']),
            'not_returned' => Tab::make('لم يعودوا')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('has_returned', false))
                ->badge($stats['not_returned']),
            'returned' => Tab::make('عادوا')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('has_returned', true))
                ->badge($stats['returned']),
        ];
    }


    public function getDefaultActiveTab(): string|int|null
    {
        return 'not_returned';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('get_disconnected_students')
                ->label('إضافة الطلاب المنقطعين')
                ->icon('heroicon-o-plus-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('إضافة الطلاب المنقطعين')
                ->modalDescription('سيتم إضافة الطلاب الذين لديهم يومان أو أكثر غياب متتاليان إلى قائمة الانقطاع.')
                ->form([
                    \Filament\Forms\Components\Select::make('excluded_groups')
                        ->label('استثناء المجموعات')
                        ->multiple()
                        ->options(Group::where('is_quran_group', true)->pluck('name', 'id'))
                        ->searchable()
                        ->preload()
                        ->helperText('اختر المجموعات غير النشطة التي لا تريد إضافة طلابها إلى قائمة الانقطاع'),
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
                        ->label('من تاريخ')
                        ->default(now()->subDays(30)->format('Y-m-d')),
                    \Filament\Forms\Components\DatePicker::make('end_date')
                        ->label('إلى تاريخ')
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
        $addedCount = $this->disconnectionService->addDisconnectedStudents($excludedGroups);

        if ($addedCount > 0) {
            Notification::make()
                ->title('تم إضافة الطلاب المنقطعين')
                ->body("تم إضافة {$addedCount} طالب إلى قائمة الانقطاع بناءً على أيام عمل المجموعة.")
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('لا يوجد طلاب منقطعين')
                ->body('لا يوجد طلاب لديهم يومان أو أكثر غياب متتاليان في أيام عمل المجموعة أو تم إضافتهم مسبقاً.')
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
