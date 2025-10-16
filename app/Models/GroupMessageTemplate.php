<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class GroupMessageTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'content',
    ];

    /**
     * The groups that belong to the message template.
     */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'group_message_template_pivot')
            ->withPivot('is_default')
            ->withTimestamps();
    }

    /**
     * Check if this template is default for a specific group
     */
    public function isDefaultForGroup(int $groupId): bool
    {
        return $this->groups()
            ->wherePivot('group_id', $groupId)
            ->wherePivot('is_default', true)
            ->exists();
    }

    /**
     * Set this template as default for a specific group
     */
    public function setAsDefaultForGroup(int $groupId): void
    {
        // First, unset any existing default templates for this group
        $this->groups()->updateExistingPivot($groupId, ['is_default' => false]);

        // Then set this template as default
        $this->groups()->updateExistingPivot($groupId, ['is_default' => true]);
    }

    /**
     * Get the list of variables that can be used in templates
     */
    public static function getVariables(): array
    {
        return [
            '{student_name}',
            '{group_name}',
            '{curr_date}',
            '{last_presence}',
        ];
    }

    /**
     * Get the labels for variables that can be used in templates
     */
    public static function getVariableLabels(): array
    {
        return [
            '{student_name}' => 'اسم الطالب',
            '{group_name}' => 'اسم المجموعة',
            '{curr_date}' => 'التاريخ الحالي',
            '{last_presence}' => 'آخر حضور',
        ];
    }

    public static function getVariableDescription(string $variable): string
    {
        return match ($variable) {
            '{student_name}' => 'اسم الطالب',
            '{group_name}' => 'اسم المجموعة',
            '{curr_date}' => 'التاريخ الحالي',
            '{last_presence}' => 'آخر حضور',
        };
    }

    /**
     * Get the values for variables based on student and group
     */
    public static function getVariableValues(Student $student, Group $group): array
    {
        // Set locale to Arabic
        Carbon::setLocale('ar');

        // Get the student's last presence date
        $lastPresence = $student->progresses()
            ->where('status', 'memorized')
            ->latest('date')
            ->first();

        $lastPresenceDate = $lastPresence
            ? Carbon::parse($lastPresence->date)->translatedFormat('l d F Y')
            : 'لم يسجل حضور بعد';

        return [
            '{student_name}' => $student->name,
            '{group_name}' => $group->name,
            '{curr_date}' => Carbon::now()->translatedFormat('l d F Y'),
            '{last_presence}' => $lastPresenceDate,
        ];
    }
}
