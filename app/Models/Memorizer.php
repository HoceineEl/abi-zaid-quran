<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Memorizer extends Model
{

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

    public function getPhotoUrlAttribute(): string
    {
        return $this->photo ? asset($this->photo) : ($this->sex == 'male' ? asset('images/default-boy.png') : asset('images/default-girl.png'));
    }

    public function getPresentTodayAttribute(): bool
    {
        return $this->attendances()->whereDate('date', now()->toDateString())->whereNotNull('check_in_time')->exists();
    }

    public function getAbsentTodayAttribute(): bool
    {
        return $this->attendances()->whereDate('date', now()->toDateString())->whereNull('check_in_time')->exists();
    }

    public function attendances(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function teacher(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }
}
