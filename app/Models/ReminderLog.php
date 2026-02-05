<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReminderLog extends Model
{
    protected $fillable = [
        'memorizer_id',
        'type',
        'phone_number',
        'message',
        'is_parent',
    ];

 

    protected static function booted(): void
    {
        static::creating(function ($model) {
            $model->created_by = auth()->id();
        });
        static::created(fn(ReminderLog $log) => $log->memorizer?->clearReminderCache());
        static::deleted(fn(ReminderLog $log) => $log->memorizer?->clearReminderCache());
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function memorizer(): BelongsTo
    {
        return $this->belongsTo(Memorizer::class);
    }
}
