<?php

namespace App\Filament\Association\Resources\GroupResource\Pages;

use Filament\Actions\EditAction;
use App\Filament\Association\Resources\GroupResource;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewGroup extends ViewRecord
{
    protected static string $resource = GroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->hidden(fn() => auth()->user()->isTeacher()),
            Action::make('export_attendance_grades')
                ->label('تصدير حضور وتقييم Excel')
                ->icon('heroicon-o-table-cells')
                ->color('primary')
                ->hidden(fn() => auth()->user()->isTeacher())
                ->schema(GroupResource::getAttendanceExportFormSchema())
                ->action(fn(array $data) => GroupResource::exportAttendanceWorkbook($this->record, $data)),
        ];
    }
}
