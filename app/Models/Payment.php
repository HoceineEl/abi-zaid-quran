<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected $casts = [
        'payment_date' => 'date',
    ];

    public function memorizer(): BelongsTo
    {
        return $this->belongsTo(Memorizer::class);
    }
}
