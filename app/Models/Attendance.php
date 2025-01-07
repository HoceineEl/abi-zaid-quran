<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'memorizer_id',
        'date',
        'check_in_time',
        'check_out_time',
        'score',
        'custom_note'
    ];

    protected $casts = [
        'date' => 'date',
        'check_in_time' => 'datetime',
        'check_out_time' => 'datetime',
        'notes' => 'array',
    ];

    public function memorizer(): BelongsTo
    {
        return $this->belongsTo(Memorizer::class, 'memorizer_id');
    }
}
