<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Memorizer extends Model
{
    use HasFactory;


    protected $with = ['payments'];


    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }


    public function hasPaymentThisMonth(): bool
    {
        return $this->payments()->whereMonth('payment_date', now()->month)->exists() || $this->exempt;
    }

    public function group(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(MemoGroup::class, 'memo_group_id');
    }
}
