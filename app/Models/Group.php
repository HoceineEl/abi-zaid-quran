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

    protected $fillable = [
        'name',
        'type',
        'message_id',
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

    public function messageTemplates(): HasMany
    {
        return $this->hasMany(GroupMessageTemplate::class);
    }

    public function getFullNameAttribute(): string
    {
        $type = $this->type === 'two_lines' ? 'سطرين' : 'نصف صفحة';

        return $this->name . ' - ' . $type;
    }
}
