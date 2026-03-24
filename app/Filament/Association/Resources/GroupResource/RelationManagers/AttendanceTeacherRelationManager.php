<?php

namespace App\Filament\Association\Resources\GroupResource\RelationManagers;

use App\Enums\AttendanceStatus;
use App\Filament\Actions\Attendance\AddNotesAction;
use App\Filament\Actions\Attendance\ClearAttendanceAction;
use App\Filament\Actions\Attendance\EditStudentAction;
use App\Filament\Actions\Attendance\JustifyPastAbsenceAction;
use App\Filament\Actions\Attendance\MarkAbsentAction;
use App\Filament\Actions\Attendance\MarkPresentAction;
use App\Filament\Actions\Attendance\SendWhatsAppAction;
use App\Models\Attendance;
use App\Models\Memorizer;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Filament\Tables\Actions\BulkAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Filament\Tables\Enums\ActionsPosition;
use Filament\Tables\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Support\Enums\ActionSize;

class AttendanceTeacherRelationManager extends RelationManager
{
    protected static string $relationship = 'memorizers';

    protected static bool $isLazy = false;

    protected static ?string $title = 'تسجيل الحضور والغياب';

    protected static ?string $icon = 'heroicon-o-user-group';

    protected function canView(Model $record): bool
    {
        return auth()->user()->isTeacher();
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->weight(FontWeight::Bold)
                    ->searchable()
                    ->icon(fn (Memorizer $record) => $this->resolveNameIcon($record))
                    ->color(fn (Memorizer $record) => $this->resolveNameColor($record))
                    ->iconPosition('before')
                    ->sortable()
                    ->label('الإسم'),

                TextColumn::make('today_status')
                    ->label('حالة اليوم')
                    ->state(function (Memorizer $record) {
                        $attendance = $record->attendances()
                            ->whereDate('date', now()->toDateString())
                            ->first();

                        return AttendanceStatus::resolveDisplayState($attendance);
                    })
                    ->badge()
                    ->formatStateUsing(function (string $state): string {
                        $status = AttendanceStatus::tryFrom($state);
                        if ($status) {
                            return $status->getLabel(); // full: "غائب غير مبرر" not short "غائب"
                        }
                        $score = \App\Enums\MemorizationScore::tryFrom($state);
                        return $score ? $score->getLabel() : $state;
                    })
                    ->color(fn (string $state): string|array|null => AttendanceStatus::getDisplayColor($state))
                    ->icon(fn (string $state): ?string => AttendanceStatus::getDisplayIcon($state))
                    ->iconPosition('before')
                    ->description(function (Memorizer $record): ?string {
                        $attendance = $record->attendances()
                            ->whereDate('date', now()->toDateString())
                            ->first();

                        if (! $attendance) {
                            return null;
                        }

                        $parts = [];

                        if (! empty($attendance->notes)) {
                            $parts[] = 'ملاحظات سلوكية';
                        }

                        if (! empty($attendance->custom_note)) {
                            $parts[] = 'ملاحظة خاصة';
                        }

                        return $parts ? implode(' · ', $parts) : null;
                    }),
            ])
            ->actions([
                SendWhatsAppAction::make(),
                MarkPresentAction::make(),
                MarkAbsentAction::make(),
                ClearAttendanceAction::make(),
                AddNotesAction::make(),
                EditStudentAction::make(),
                JustifyPastAbsenceAction::make(),
            ], ActionsPosition::BeforeColumns)
            ->headerActions([
                $this->exportTableAction(),
            ])
            ->bulkActions([
                $this->markPresentBulkAction(),
                $this->markAbsentBulkAction(),
                $this->revertAttendanceBulkAction(),
            ])
            ->paginated(false);
    }

    // ─── Name Column Helpers ───────────────────────────────────────────

    private function resolveNameIcon(Memorizer $record): string
    {
        $attendance = $record->attendances()
            ->whereDate('date', now()->toDateString())
            ->first();

        $status = AttendanceStatus::resolve($attendance);

        return $status === AttendanceStatus::UNMARKED
            ? 'heroicon-o-clock'
            : $status->getIcon();
    }

    private function resolveNameColor(Memorizer $record): string
    {
        $attendance = $record->attendances()
            ->whereDate('date', now()->toDateString())
            ->first();

        $status = AttendanceStatus::resolve($attendance);

        return $status === AttendanceStatus::UNMARKED
            ? ''
            : $status->getColor();
    }

    // ─── Header Actions ────────────────────────────────────────────────

    private function exportTableAction(): Action
    {
        return Action::make('export_table')
            ->label('إرسال التقرير اليومي')
            ->icon('heroicon-o-share')
            ->size(ActionSize::Small)
            ->color('success')
            ->action(function () {
                $date = now()->format('Y-m-d');

                $memorizers = $this->ownerRecord->memorizers()
                    ->with(['attendances' => fn ($q) => $q->whereDate('date', $date)])
                    ->get();

                $presentCount = $memorizers->filter(
                    fn ($m) => $m->attendances->first()?->check_in_time !== null
                )->count();

                $presencePercentage = $memorizers->count() > 0
                    ? round(($presentCount / $memorizers->count()) * 100)
                    : 0;

                $html = view('components.attendance-export-table', [
                    'memorizers' => $memorizers,
                    'group' => $this->ownerRecord,
                    'date' => $date,
                ])->render();

                $this->dispatch('export-table', [
                    'html' => $html,
                    'groupName' => $this->ownerRecord->name,
                    'presencePercentage' => $presencePercentage,
                ]);
            });
    }

    // ─── Bulk Actions ──────────────────────────────────────────────────

    private function markPresentBulkAction(): BulkAction
    {
        return BulkAction::make('mark_attendance_bulk')
            ->label('حاضرين')
            ->icon(AttendanceStatus::PRESENT->getIcon())
            ->color(AttendanceStatus::PRESENT->getColor())
            ->size(ActionSize::ExtraSmall)
            ->action(function ($livewire) {
                $records = Memorizer::find($livewire->getSelectedTableRecords());
                $records->each(function (Memorizer $memorizer) {
                    Attendance::firstOrCreate([
                        'memorizer_id' => $memorizer->id,
                        'date' => now()->toDateString(),
                    ], [
                        'check_in_time' => now()->toTimeString(),
                    ]);
                });

                Notification::make()
                    ->title('تم تسجيل الحضور بنجاح للطلاب المحددين')
                    ->success()
                    ->send();
            });
    }

    private function markAbsentBulkAction(): BulkAction
    {
        return BulkAction::make('mark_absence_bulk')
            ->label('غائبين')
            ->icon(AttendanceStatus::ABSENT_UNJUSTIFIED->getIcon())
            ->color(AttendanceStatus::ABSENT_UNJUSTIFIED->getColor())
            ->size(ActionSize::ExtraSmall)
            ->requiresConfirmation()
            ->modalHeading('تأكيد تسجيل الغياب الجماعي')
            ->modalDescription('')
            ->modalSubmitActionLabel('تأكيد الغياب للجميع')
            ->form([
                Toggle::make('absence_justified')
                    ->label('غياب مبرر؟')
                    ->helperText('فعّل هذا الخيار إذا كان الغياب بعذر مقبول لجميع الطلاب المحددين.')
                    ->default(false)
                    ->onIcon('heroicon-m-shield-check')
                    ->offIcon('heroicon-m-x-circle')
                    ->onColor(AttendanceStatus::ABSENT_JUSTIFIED->getColor())
                    ->offColor(AttendanceStatus::ABSENT_UNJUSTIFIED->getColor()),
            ])
            ->action(function ($livewire, array $data) {
                $justified = $data['absence_justified'] ?? false;
                $records = Memorizer::find($livewire->getSelectedTableRecords());
                $records->each(function (Memorizer $memorizer) use ($justified) {
                    Attendance::updateOrCreate(
                        [
                            'memorizer_id' => $memorizer->id,
                            'date' => now()->toDateString(),
                        ],
                        [
                            'check_in_time' => null,
                            'absence_justified' => $justified,
                        ]
                    );
                });

                Notification::make()
                    ->title($justified
                        ? 'تم تسجيل الغياب المبرر بنجاح للطلاب المحددين'
                        : 'تم تسجيل الغياب بنجاح للطلاب المحددين')
                    ->success()
                    ->send();
            });
    }

    private function revertAttendanceBulkAction(): BulkAction
    {
        return BulkAction::make('revert_attendance_bulk')
            ->label('إلغاء')
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('warning')
            ->size(ActionSize::ExtraSmall)
            ->requiresConfirmation()
            ->modalHeading('تأكيد إلغاء التسجيل الجماعي')
            ->modalDescription('هل أنت متأكد من إلغاء تسجيل الحضور/الغياب للطلاب المحددين؟')
            ->modalSubmitActionLabel('تأكيد الإلغاء')
            ->action(function ($livewire) {
                $records = Memorizer::find($livewire->getSelectedTableRecords());
                $records->each(function (Memorizer $memorizer) {
                    $memorizer->attendances()
                        ->whereDate('date', now()->toDateString())
                        ->delete();
                });

                Notification::make()
                    ->title('تم إلغاء التسجيل بنجاح للطلاب المحددين')
                    ->success()
                    ->send();
            });
    }
}
