<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupAutomationRun extends Model
{
    protected $fillable = [
        'group_id',
        'run_date',
        'phase',
        'status',
        'details',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'run_date' => 'date',
        'details' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }
}
