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
use Filament\Tables\Actions\Action;

class AddNotesAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'add_notes';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->tooltip('إضافة ملاحظات وتقييم')
            ->label('')
            ->icon('heroicon-o-document-text')
            ->size(ActionSize::ExtraLarge)
            ->color('info')
            ->slideOver()
            ->modalSubmitActionLabel('حفظ')
            ->form([
                Section::make('تقييم الحفظ')
                    ->schema([
                        ToggleButtons::make('score')
                            ->label('تقييم اليوم')
                            ->columnSpanFull()
                            ->inline()
                            ->options(MemorizationScore::class)
                            ->required()
                            ->default(function (Memorizer $record) {
                                $attendance = $record->attendances()
                                    ->whereDate('date', now()->toDateString())
                                    ->first();

                                return $attendance?->score ?? MemorizationScore::GOOD;
                            }),
                    ]),

                Section::make('ملاحظات السلوك')
                    ->description('حدد السلوكيات التي ظهرت اليوم')
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
                            ->default(function (Memorizer $record) {
                                $attendance = $record->attendances()
                                    ->whereDate('date', now()->toDateString())
                                    ->first();

                                return ($attendance && $attendance->notes !== null)
                                    ? $attendance->notes
                                    : [];
                            }),

                        Textarea::make('custom_note')
                            ->label('ملاحظات إضافية')
                            ->placeholder('أضف أي ملاحظات إضافية هنا...')
                            ->default(function (Memorizer $record) {
                                $attendance = $record->attendances()
                                    ->whereDate('date', now()->toDateString())
                                    ->first();

                                return ($attendance && $attendance->custom_note)
                                    ? $attendance->custom_note
                                    : '';
                            })
                            ->rows(3),
                    ]),
            ])
            ->visible(fn (Memorizer $record) => $record->attendances()
                ->whereDate('date', now()->toDateString())
                ->where('check_in_time', '!=', null)
                ->exists())
            ->action(function (Memorizer $record, array $data): void {
                $saved = AttendanceActionService::saveNotes($record, $data);

                if (! $saved) {
                    return;
                }

                if (! empty($data['behavioral_issues'])) {
                    $ownerRecord = $this->getLivewire()->getOwnerRecord();

                    AttendanceActionService::notifyAdminsOfTroubles(
                        $record,
                        $data['behavioral_issues'],
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

                Notification::make()
                    ->title('تم حفظ الملاحظات بنجاح')
                    ->success()
                    ->send();
            });
    }
}
