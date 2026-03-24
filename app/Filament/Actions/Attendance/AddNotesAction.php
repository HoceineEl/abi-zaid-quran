<?php

namespace App\Filament\Actions\Attendance;

use App\Enums\MemorizationScore;
use App\Enums\Troubles;
use App\Models\Memorizer;
use App\Models\User;
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
                $attendance = $record->attendances()
                    ->whereDate('date', now()->toDateString())
                    ->first();

                if (! $attendance) {
                    return;
                }

                $attendance->update([
                    'notes' => $data['behavioral_issues'],
                    'score' => $data['score'],
                    'custom_note' => $data['custom_note'],
                ]);

                $this->notifyAdminsIfTroubles($record, $data);

                Notification::make()
                    ->title('تم حفظ الملاحظات بنجاح')
                    ->success()
                    ->send();
            });
    }

    /**
     * Send notifications to association admins when behavioral issues are recorded.
     */
    private function notifyAdminsIfTroubles(Memorizer $record, array $data): void
    {
        if (empty($data['behavioral_issues'])) {
            return;
        }

        $associationAdmins = User::where('email', 'LIKE', '%@association.com')->get();

        $troublesLabels = collect($data['behavioral_issues'])
            ->map(fn (string $trouble) => Troubles::tryFrom($trouble)?->getLabel())
            ->filter()
            ->implode('، ');

        $ownerRecord = $this->getLivewire()->getOwnerRecord();

        Notification::make()
            ->title("مشكلة سلوكية للطالب {$record->name}")
            ->body("قام الطالب {$record->name} في مجموعة {$ownerRecord->name} بـ {$troublesLabels} بتاريخ " . now()->format('Y-m-d'))
            ->warning()
            ->actions([
                NotificationAction::make('view_attendance')
                    ->label('عرض الحضور')
                    ->url(fn () => \App\Filament\Association\Resources\GroupResource::getUrl(
                        'view',
                        ['record' => $ownerRecord, 'activeRelationManager' => '0'],
                        panel: 'association'
                    )),
            ])
            ->sendToDatabase($associationAdmins);
    }
}
