<?php

namespace App\Models;

use App\Enums\DisconnectionStatus;
use App\Enums\MessageResponseStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentDisconnection extends Model
{
    use HasFactory;


    protected $casts = [
        'disconnection_date' => 'date',
        'contact_date' => 'date',
        'message_response' => MessageResponseStatus::class,
        'has_returned' => 'boolean',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class)->withoutGlobalScope('userGroups');
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
        if ($this->contact_date && $this->message_response === MessageResponseStatus::Yes) {
            return DisconnectionStatus::Responded;
        }

        if ($this->contact_date) {
            return DisconnectionStatus::Contacted;
        }

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
