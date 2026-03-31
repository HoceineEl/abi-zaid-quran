<?php

namespace App\Filament\Actions;

use App\Enums\MessageSubmissionType;
use App\Models\Group;
use App\Services\WhatsAppAttendanceService;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Toggle;
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
                    $result = $this->fetchAndMatchAttendees($this->resolveDate($get));
                    $set('student_ids', $result['matched_student_ids']);
                }),
            Step::make('confirm_attendees')
                ->label('تأكيد الحضور')
                ->icon('heroicon-o-user-group')
                ->schema([
                    Placeholder::make('match_stats')
                        ->label('إحصائيات المطابقة')
                        ->content(function (Get $get) {
                            $result = $this->fetchAndMatchAttendees($this->resolveDate($get));

                            return "تم مطابقة {$result['matched_count']} من {$result['total_students']} طالب";
                        }),
                    CheckboxList::make('student_ids')
                        ->label('الطلاب')
                        ->options(fn () => $this->getOwnerGroup()
                            ->students()
                            ->orderBy('order_no')
                            ->pluck('name', 'id'))
                        ->descriptions(fn (Get $get) => $this->fetchAndMatchAttendees($this->resolveDate($get))['descriptions'])
                        ->disableOptionWhen(fn (string $value, Get $get) => in_array(
                            (int) $value,
                            $this->fetchAndMatchAttendees($this->resolveDate($get))['already_present_ids'],
                        ))
                        ->bulkToggleable()
                        ->columns(2)
                        ->required(),
                    Toggle::make('mark_others_absent')
                        ->label('تسجيل البقية كغائبين')
                        ->default(false),
                ]),
        ];
    }

    protected function fetchAndMatchAttendees(string $date): array
    {
        static $cache = [];

        if (isset($cache[$date])) {
            return $cache[$date];
        }

        $group = $this->getOwnerGroup()->load([
            'students.progresses' => fn ($query) => $query
                ->where('date', $date)
                ->select(['id', 'student_id', 'date', 'status', 'with_reason', 'comment']),
        ]);
        $students = $group->students()->orderBy('order_no')->get()->keyBy('id');
        $preview = app(WhatsAppAttendanceService::class)->buildGroupPreview($group, $date);
        $matchedStudentIds = collect($preview['matched_student_ids'] ?? []);
        $alreadyPresentIds = collect($preview['already_present_ids'] ?? []);
        $descriptions = [];

        foreach ($students as $student) {
            if ($alreadyPresentIds->contains($student->id)) {
                $descriptions[$student->id] = "{$student->phone} — مسجل مسبقاً";

                continue;
            }

            if ($matchedStudentIds->contains($student->id)) {
                $descriptions[$student->id] = "{$student->phone} — تم المطابقة من واتساب";

                continue;
            }

            $descriptions[$student->id] = $student->phone;
        }

        return $cache[$date] = [
            'matched_student_ids' => $matchedStudentIds->all(),
            'already_present_ids' => $alreadyPresentIds->all(),
            'descriptions' => $descriptions,
            'matched_count' => $matchedStudentIds->count(),
            'total_students' => $students->count(),
        ];
    }

    protected function processAttendance(array $data): void
    {
        $group = $this->getOwnerGroup();
        $date = $data['date'] ?? today()->format('Y-m-d');
        $studentIds = $data['student_ids'] ?? [];
        $markOthersAbsent = (bool) ($data['mark_others_absent'] ?? false);

        if (empty($studentIds)) {
            Notification::make()
                ->warning()
                ->title('لم يتم اختيار أي طالب')
                ->send();

            return;
        }

        $preview = app(WhatsAppAttendanceService::class)->buildGroupPreview($group, $date);
        $preview['to_mark_present_ids'] = array_values(array_map('intval', $studentIds));
        $preview['matched_student_ids'] = array_values(array_unique([
            ...($preview['already_present_ids'] ?? []),
            ...$preview['to_mark_present_ids'],
        ]));
        $result = app(WhatsAppAttendanceService::class)->applyGroupPreview(
            $group->load([
                'students.progresses' => fn ($query) => $query
                    ->where('date', $date)
                    ->select(['id', 'student_id', 'date', 'status', 'with_reason', 'comment']),
            ]),
            $preview,
            $date,
            $markOthersAbsent,
            false,
        );

        $title = $markOthersAbsent
            ? "تم تسجيل حضور {$result['students_marked_present']} طالب وتسجيل {$result['students_marked_absent']} غائبين بنجاح"
            : "تم تسجيل حضور {$result['students_marked_present']} طالب بنجاح";

        Notification::make()
            ->success()
            ->title($title)
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
}
