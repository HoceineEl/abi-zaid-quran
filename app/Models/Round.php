<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Round extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'days',
    ];

    protected $casts = [
        'days' => 'array',
    ];


    public function memorizers(): HasMany
    {
        return $this->hasMany(Memorizer::class);
    }
}
