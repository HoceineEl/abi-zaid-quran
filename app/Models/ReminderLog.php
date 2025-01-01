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

    public function memorizer(): BelongsTo
    {
        return $this->belongsTo(Memorizer::class);
    }
}
