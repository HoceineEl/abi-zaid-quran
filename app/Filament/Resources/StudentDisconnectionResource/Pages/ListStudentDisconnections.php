<?php

namespace App\Filament\Resources\StudentDisconnectionResource\Pages;

use App\Filament\Exports\StudentDisconnectionExporter;
use App\Exports\StudentDisconnectionExport;
use App\Filament\Resources\StudentDisconnectionResource;
use App\Models\Group;
use App\Models\Student;
use App\Models\StudentDisconnection;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Notifications\Notification;

class ListStudentDisconnections extends ListRecords
{
    protected static string $resource = StudentDisconnectionResource::class;

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
                    $this->addDisconnectedStudents($data['excluded_groups']);
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
                ])
                ->action(function (array $data) {
                    $startDate = $data['start_date'];
                    $endDate = $data['end_date'];
                    $dateRange = "من {$startDate} إلى {$endDate}";

                    $export = new StudentDisconnectionExport($dateRange, $startDate, $endDate);
                    return \Maatwebsite\Excel\Facades\Excel::download($export, 'students-disconnection-' . now()->format('Y-m-d') . '.xlsx');
                }),
        ];
    }

    private function addDisconnectedStudents(array $excludedGroups = []): void
    {
        $students = Student::with(['group', 'progresses'])
            ->whereHas('group', function ($query) {
                $query->where('is_quran_group', true);
            })
            ->whereHas('progresses', function ($query) {
                $query->where('status', 'absent')
                    ->where(function ($q) {
                        $q->where('with_reason', 0)
                            ->orWhereNull('with_reason');
                    });
            })
            ->when(!empty($excludedGroups), function ($query) use ($excludedGroups) {
                $query->whereNotIn('group_id', $excludedGroups);
            })
            ->get()
            ->filter(function ($student) {
                $recentProgresses = $student->progresses()
                    ->latest('date')
                    ->limit(30)
                    ->orderBy('date', 'asc')
                    ->get();

                $consecutiveAbsentDays = 0;
                foreach ($recentProgresses as $progress) {
                    if ($progress->status === 'absent' && (int)$progress->with_reason === 0) {
                        $consecutiveAbsentDays++;
                    } else if ($progress->status === 'memorized' || ($progress->status === 'absent' && (int)$progress->with_reason === 1)) {
                        break;
                    }
                }

                return $consecutiveAbsentDays >= 2;
            })
            ->filter(function ($student) {
                return !StudentDisconnection::where('student_id', $student->id)->exists();
            });

        $addedCount = 0;
        foreach ($students as $student) {
            $disconnectionDate = $student->getDisconnectionDateAttribute();

            if ($disconnectionDate) {
                StudentDisconnection::create([
                    'student_id' => $student->id,
                    'group_id' => $student->group_id,
                    'disconnection_date' => $disconnectionDate,
                ]);
                $addedCount++;
            }
        }

        if ($addedCount > 0) {
            Notification::make()
                ->title('تم إضافة الطلاب المنقطعين')
                ->body("تم إضافة {$addedCount} طالب إلى قائمة الانقطاع.")
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('لا يوجد طلاب منقطعين')
                ->body('لا يوجد طلاب لديهم يومان أو أكثر غياب متتاليان أو تم إضافتهم مسبقاً.')
                ->info()
                ->send();
        }
    }
}
