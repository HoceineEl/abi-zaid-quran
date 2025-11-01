<?php

namespace App\Models;

use App\Enums\DisconnectionStatus;
use App\Enums\MessageResponseStatus;
use App\Enums\StudentReactionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentDisconnection extends Model
{
    use HasFactory;


    protected $casts = [
        'disconnection_date' => 'date',
        'contact_date' => 'date',
        'reminder_message_date' => 'date',
        'warning_message_date' => 'date',
        'message_response' => MessageResponseStatus::class,
        'student_reaction' => StudentReactionStatus::class,
        'student_reaction_date' => 'date',
        'has_returned' => 'boolean',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class)
            ->withoutGlobalScope('userGroups')
            ->withTrashed();
    }

    public function getStudentNameAttribute(): string
    {
        return $this->student->name ?? '';
    }

    public function getGroupNameAttribute(): string
    {
        return $this->group->name ?? '';
    }

    public function getStatusAttribute(): DisconnectionStatus
    {
        // If student reacted positively → Responded
        if ($this->student_reaction === StudentReactionStatus::PositiveResponse) {
            return DisconnectionStatus::Responded;
        }

        // If any reaction exists (except no response) → Contacted
        if ($this->student_reaction && $this->student_reaction !== StudentReactionStatus::NoResponse) {
            return DisconnectionStatus::Contacted;
        }

        // If any message was sent → Contacted
        if ($this->contact_date) {
            return DisconnectionStatus::Contacted;
        }

        // Default → Disconnected
        return DisconnectionStatus::Disconnected;
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->status->getLabel();
    }

    public function getStatusColorAttribute(): string
    {
        return $this->status->getColor();
    }

    public function getStatusIconAttribute(): string
    {
        return $this->status->getIcon();
    }
}
