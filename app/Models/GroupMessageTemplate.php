<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupMessageTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'content',
        'is_default',
        'group_id',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }
}
