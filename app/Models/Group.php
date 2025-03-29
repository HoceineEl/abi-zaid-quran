<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Group extends Model
{
    use HasFactory;



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
}
