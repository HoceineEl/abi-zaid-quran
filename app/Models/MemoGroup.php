<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MemoGroup extends Model
{
    use HasFactory;

    protected $with = ['memorizers'];

    public function memorizers(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Memorizer::class);
    }
}
