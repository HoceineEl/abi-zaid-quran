<?php

namespace App\Filament\Actions\Attendance;

use App\Enums\MemorizationScore;
use App\Enums\Troubles;
use App\Models\Memorizer;
use App\Services\AttendanceActionService;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Filament\Support\Enums\ActionSize;
use Filament\Tables\Actions\BulkAction;
use Illuminate\Database\Eloquent\Collection;

class BulkAddNotesAction extends BulkAction
{
    public static function getDefaultName(): ?string
    {
        return 'add_notes_bulk';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('ملاحظات وتقييم')
            ->icon('heroicon-o-document-text')
            ->color('info')
            ->size(ActionSize::ExtraSmall)
            ->slideOver()
            ->modalHeading('إضافة ملاحظات وتقييم جماعي')
            ->modalDescription('سيتم تطبيق نفس التقييم والملاحظات على جميع الطلاب المحددين الذين تم تسجيل حضورهم اليوم. سيتم تخطي من لم يسجل حضوره.')
            ->modalSubmitActionLabel('حفظ للجميع')
            ->deselectRecordsAfterCompletion()
            ->form([
                Section::make('تقييم الحفظ')
                    ->schema([
                        ToggleButtons::make('score')
                            ->label('تقييم اليوم')
                            ->columnSpanFull()
                            ->inline()
                            ->options(MemorizationScore::class)
                            ->required()
                            ->default(MemorizationScore::GOOD),
                    ]),

                Section::make('ملاحظات السلوك')
                    ->description('حدد السلوكيات التي ظهرت اليوم (اختياري - سيتم تطبيقها على جميع الطلاب المحددين)')
                    ->compact()
                    ->collapsed()
                    ->collapsible()
                    ->schema([
                        ToggleButtons::make('behavioral_issues')
                            ->label('')
                            ->options(Troubles::class)
                            ->inline()
                            ->multiple()
                            ->columnSpanFull()
                            ->default([]),

                        Textarea::make('custom_note')
                            ->label('ملاحظات إضافية')
                            ->placeholder('أضف أي ملاحظات إضافية هنا...')
                            ->rows(3),
                    ]),
            ])
            ->action(function (Collection $records, array $data): void {
                $saved = 0;
                $skipped = 0;
                $troubleEntries = [];

                $records->loadMissing('guardian');

                foreach ($records as $memorizer) {
                    /** @var Memorizer $memorizer */
                    $success = AttendanceActionService::saveNotes($memorizer, $data);

                    if (! $success) {
                        $skipped++;
                        continue;
                    }

                    $saved++;

                    if (! empty($data['behavioral_issues'])) {
                        $troubleEntries[] = [
                            'memorizer' => $memorizer,
                            'troubles' => $data['behavioral_issues'],
                        ];
                    }
                }

                if (! empty($troubleEntries)) {
                    $ownerRecord = $this->getLivewire()->getOwnerRecord();

                    AttendanceActionService::notifyAdminsOfBulkTroubles(
                        $troubleEntries,
                        $ownerRecord->name,
                        NotificationAction::make('view_attendance')
                            ->label('عرض الحضور')
                            ->url(fn () => \App\Filament\Association\Resources\GroupResource::getUrl(
                                'view',
                                ['record' => $ownerRecord, 'activeRelationManager' => '0'],
                                panel: 'association'
                            )),
                    );
                }

                $this->sendResultNotification($saved, $skipped);
            });
    }

    private function sendResultNotification(int $saved, int $skipped): void
    {
        if ($saved === 0 && $skipped > 0) {
            Notification::make()
                ->title('لم يتم حفظ أي ملاحظة')
                ->body("تم تخطي {$skipped} طالب لعدم تسجيل حضورهم اليوم.")
                ->warning()
                ->send();

            return;
        }

        $title = "تم حفظ الملاحظات لـ {$saved} طالب";
        if ($skipped > 0) {
            $title .= " (تم تخطي {$skipped} لعدم تسجيل حضورهم)";
        }

        Notification::make()
            ->title($title)
            ->success()
            ->send();
    }
}
