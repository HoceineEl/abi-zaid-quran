<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Memorizer extends Model
{
    use HasFactory;




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

    public function  attendances(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Attendance::class);
    }
}
