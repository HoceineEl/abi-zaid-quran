<?php

namespace App\Models;

use App\Enums\MessageSubmissionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class Group extends Model
{
    use HasFactory, SoftDeletes;


    protected $casts = [
        'is_onsite' => 'boolean',
        'ignored_names_phones' => 'array',
        'message_submission_type' => MessageSubmissionType::class,
    ];

    protected $fillable = [
        'name',
        'type',
        'is_onsite',
        'message_id',
        'message_submission_type',
        'ignored_names_phones',
    ];



    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function managers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'group_manager', 'group_id', 'manager_id')->where('role', '!=', 'teacher');
    }

    public function progresses(): HasManyThrough
    {
        return $this->hasManyThrough(Progress::class, Student::class);
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }



    public function messageTemplates(): BelongsToMany
    {
        return $this->belongsToMany(GroupMessageTemplate::class, 'group_message_template_pivot')
            ->withPivot('is_default')
            ->withTimestamps();
    }

    /**
     * Get the default message template for this group
     */
    public function getDefaultMessageTemplate(): ?GroupMessageTemplate
    {
        return $this->messageTemplates()
            ->wherePivot('is_default', true)
            ->first();
    }

    /**
     * Set a message template as default for this group
     */
    public function setDefaultMessageTemplate(int $templateId): void
    {
        // First, unset any existing default templates
        $this->messageTemplates()
            ->wherePivot('is_default', true)
            ->updateExistingPivot($templateId, ['is_default' => false]);

        // Then set the new default template
        $this->messageTemplates()
            ->updateExistingPivot($templateId, ['is_default' => true]);
    }

    public function getFullNameAttribute(): string
    {
        $type = $this->type === 'two_lines' ? 'سطرين' : 'نصف صفحة';

        return $this->name . ' - ' . $type;
    }

    public static function getDailyAttendanceSummary(string $date)
    {
        return self::query()
            ->whereHas('students.progresses', function ($query) use ($date) {
                $query->where('date', $date);
            })
            ->withCount('students')
            ->with(['students.progresses' => function ($query) use ($date) {
                $query->where('date', $date);
            }])
            ->get()
            ->map(function ($group) {
                $presentCount = $group->students->filter(function ($student) {
                    return $student->progresses->where('status', 'memorized')->isNotEmpty();
                })->count();

                $absentCount = $group->students_count - $presentCount;

                return [
                    'id' => $group->id,
                    'name' => $group->name,
                    'present' => $presentCount,
                    'absent' => $absentCount,
                ];
            });
    }
}
