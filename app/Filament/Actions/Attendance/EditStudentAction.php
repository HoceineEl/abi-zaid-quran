<?php

namespace App\Filament\Actions\Attendance;

use Filament\Actions\Action;
use App\Models\Memorizer;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;

class EditStudentAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'edit_student';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->tooltip('تعديل معلومات الطالب')
            ->label('')
            ->icon('heroicon-o-pencil-square')
            ->color('info')
            ->form([
                DatePicker::make('birth_date')
                    ->label('تاريخ الميلاد')
                    ->required(),
            ])
            ->fillForm(fn (Memorizer $record): array => [
                'birth_date' => $record->birth_date,
            ])
            ->action(function (Memorizer $record, array $data): void {
                $record->update([
                    'birth_date' => $data['birth_date'],
                ]);

                Notification::make()
                    ->title('تم تحديث معلومات الطالب بنجاح')
                    ->success()
                    ->send();
            });
    }
}
