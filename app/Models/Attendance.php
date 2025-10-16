<?php

namespace App\Models;

use App\Enums\MemorizationScore;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'memorizer_id',
        'date',
        'check_in_time',
        'check_out_time',
        'score',
        'custom_note',
    ];

    protected $casts = [
        'date' => 'date',
        'check_in_time' => 'datetime',
        'check_out_time' => 'datetime',
        'notes' => 'array',
        'score' => MemorizationScore::class,
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->created_by = auth()->id();
        });
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function memorizer(): BelongsTo
    {
        return $this->belongsTo(Memorizer::class, 'memorizer_id');
    }

    public function group(): HasOneThrough
    {
        return $this->hasOneThrough(MemoGroup::class, Memorizer::class, 'id', 'id', 'memorizer_id', 'memo_group_id');
    }
}
