<?php

namespace App\Filament\Actions;

use App\Models\Group;
use App\Models\Student;
use App\Models\StudentDisconnection;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\BulkAction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class MoveDisconnectedStudentToGroupAction extends BulkAction
{
    public static function getDefaultName(): ?string
    {
        return 'move_to_group';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('نقل إلى مجموعة إجبارية')
            ->icon('heroicon-o-arrow-right')
            ->color('warning')
            ->form([
                Forms\Components\Select::make('target_group_id')
                    ->label('اختر المجموعة المستهدفة')
                    ->relationship('group', 'name', modifyQueryUsing: fn ($query) => $query->withoutGlobalScope('userGroups'))
                    ->searchable()
                    ->required(),
            ])
            ->action(function (Collection $records, array $data) {
                $this->duplicateStudentsToGroup($records, $data['target_group_id']);
            })
            ->requiresConfirmation()
            ->modalHeading('نقل الطلاب إلى مجموعة إجبارية')
            ->modalDescription('سيتم نقل الطلاب المحددين إلى المجموعة المختارة مع الاحتفاظ بهم في مجموعتهم الأصلية');
    }

    protected function duplicateStudentsToGroup(Collection $disconnections, int $targetGroupId): void
    {
        $targetGroup = Group::withoutGlobalScope('userGroups')->find($targetGroupId);

        if (!$targetGroup) {
            Notification::make()
                ->title('خطأ')
                ->body('المجموعة المختارة غير موجودة')
                ->danger()
                ->send();
            return;
        }

        $duplicatedCount = 0;
        $skippedCount = 0;
        $errors = [];

        foreach ($disconnections as $disconnection) {
            try {
                $originalStudent = $disconnection->student;

                // Check if student already exists in target group
                $existingStudent = Student::where('group_id', $targetGroupId)
                    ->where(function ($query) use ($originalStudent) {
                        $query->where('name', $originalStudent->name)
                            ->orWhere('phone', $originalStudent->phone);
                    })
                    ->first();

                if ($existingStudent) {
                    $skippedCount++;
                    Log::info('Student already exists in target group', [
                        'student_id' => $originalStudent->id,
                        'student_name' => $originalStudent->name,
                        'target_group_id' => $targetGroupId,
                    ]);
                    continue;
                }

                // Create new student in target group
                $newStudent = Student::create([
                    'name' => $originalStudent->name,
                    'phone' => $originalStudent->phone,
                    'group_id' => $targetGroupId,
                    'with_reason' => $originalStudent->with_reason ?? false,
                ]);

                // Mark the disconnection record as converted to mandatory group
                $disconnection->update([
                    'has_been_converted_to_mandatory_group' => true,
                ]);

                $duplicatedCount++;

                Log::info('Student duplicated to new group', [
                    'original_student_id' => $originalStudent->id,
                    'new_student_id' => $newStudent->id,
                    'target_group_id' => $targetGroupId,
                    'student_name' => $originalStudent->name,
                ]);

            } catch (\Exception $e) {
                $skippedCount++;
                $errors[] = "خطأ في نسخ الطالب {$disconnection->student->name}: {$e->getMessage()}";

                Log::error('Failed to duplicate student to target group', [
                    'student_id' => $disconnection->student->id,
                    'target_group_id' => $targetGroupId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Build notification message
        $message = "تم نقل $duplicatedCount طالب إلى المجموعة الإجبارية {$targetGroup->name}.";

        if ($skippedCount > 0) {
            $message .= " تم تخطي $skippedCount طالب (موجودون بالفعل أو حدثت أخطاء).";
        }

        if (!empty($errors)) {
            $message .= "\n\nالأخطاء:\n" . implode("\n", array_slice($errors, 0, 3));
            if (count($errors) > 3) {
                $message .= "\n... و " . (count($errors) - 3) . " أخطاء أخرى";
            }
        }

        Notification::make()
            ->title($duplicatedCount > 0 ? 'تم بنجاح!' : 'لم يتم نقل أي طالب')
            ->body($message)
            ->color($duplicatedCount > 0 ? 'success' : 'warning')
            ->send();
    }
}
