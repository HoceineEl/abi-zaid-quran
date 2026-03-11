<?php

namespace App\Filament\Actions;

use App\Enums\MessageSubmissionType;
use App\Models\Group;
use App\Services\GroupWhatsAppAutomationService;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Wizard\Step;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;

class AutoAttendanceAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'auto_attendance';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('حضور تلقائي')
            ->icon('heroicon-o-signal')
            ->color('success')
            ->steps($this->getWizardSteps())
            ->modalSubmitActionLabel('تأكيد الحضور')
            ->action(fn (array $data) => $this->processAttendance($data));
    }

    protected function getWizardSteps(): array
    {
        return [
            Step::make('settings')
                ->label('الإعدادات')
                ->icon('heroicon-o-cog-6-tooth')
                ->schema([
                    DatePicker::make('date')
                        ->label('تاريخ الحضور')
                        ->default(today())
                        ->required()
                        ->native(false)
                        ->displayFormat('Y-m-d'),
                    Placeholder::make('whatsapp_group_info')
                        ->label('مجموعة واتساب المرتبطة')
                        ->content(fn () => $this->getOwnerGroup()->whatsapp_group_jid ?? 'لا توجد مجموعة مرتبطة'),
                    Placeholder::make('accepted_message_types')
                        ->label('نوع الرسائل المقبولة للتسجيل')
                        ->content(fn () => ($this->getOwnerGroup()->message_submission_type ?? MessageSubmissionType::Media)->getLabel()),
                ])
                ->afterValidation(function (Get $get, Set $set) {
                    $result = $this->automation()->previewMatches($this->getOwnerGroup(), $this->resolveDate($get));
                    $set('student_ids', $result['matched_student_ids']);
                }),
            Step::make('confirm_attendees')
                ->label('تأكيد الحضور')
                ->icon('heroicon-o-user-group')
                ->schema([
                    Placeholder::make('match_stats')
                        ->label('إحصائيات المطابقة')
                        ->content(function (Get $get) {
                            $result = $this->automation()->previewMatches($this->getOwnerGroup(), $this->resolveDate($get));

                            return "تم مطابقة {$result['matched_count']} من {$result['total_students']} طالب";
                        }),
                    CheckboxList::make('student_ids')
                        ->label('الطلاب')
                        ->options(fn () => $this->getOwnerGroup()
                            ->students()
                            ->orderBy('order_no')
                            ->pluck('name', 'id'))
                        ->descriptions(fn (Get $get) => $this->automation()->previewMatches($this->getOwnerGroup(), $this->resolveDate($get))['descriptions'])
                        ->disableOptionWhen(fn (string $value, Get $get) => in_array(
                            (int) $value,
                            $this->automation()->previewMatches($this->getOwnerGroup(), $this->resolveDate($get))['already_present_ids'],
                        ))
                        ->bulkToggleable()
                        ->columns(2)
                        ->required(),
                ]),
        ];
    }

    protected function processAttendance(array $data): void
    {
        $group = $this->getOwnerGroup();
        $date = $data['date'] ?? today()->format('Y-m-d');
        $studentIds = $data['student_ids'] ?? [];

        if (empty($studentIds)) {
            Notification::make()
                ->warning()
                ->title('لم يتم اختيار أي طالب')
                ->send();

            return;
        }

        $createdCount = $this->automation()->markAttendance($group, $date, $studentIds);

        Notification::make()
            ->success()
            ->title("تم تسجيل حضور {$createdCount} طالب بنجاح")
            ->send();
    }

    protected function resolveDate(Get $get): string
    {
        return $get('date') ?? today()->format('Y-m-d');
    }

    protected function getOwnerGroup(): Group
    {
        return $this->getLivewire()->ownerRecord;
    }

    protected function automation(): GroupWhatsAppAutomationService
    {
        return app(GroupWhatsAppAutomationService::class);
    }
}
