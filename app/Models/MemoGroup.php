<?php

namespace App\Models;

use App\Enums\Days;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MemoGroup extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'price', 'teacher_id', 'days'];

    protected $casts = [
        'days' => 'array',
    ];

    public function memorizers(): HasMany
    {
        return $this->hasMany(Memorizer::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function getArabicDaysAttribute(): string
    {
        if (!$this->days) {
            return '';
        }

        $arabicDays = array_map(
            fn($day) => Days::from($day)->getLabel(),
            $this->days
        );

        return implode(' و ', $arabicDays);
    }
}
