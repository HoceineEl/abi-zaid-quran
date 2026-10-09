<?php

namespace App\Models;

use App\Enums\PaymentType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected $casts = [
        'payment_date' => 'date',
        'type' => PaymentType::class,
    ];

    protected $attributes = [
        'type' => PaymentType::MONTHLY->value,
    ];

    protected static function booted(): void
    {
        static::created(fn (Payment $payment) => $payment->memorizer?->clearPaymentCache());
        static::deleted(fn (Payment $payment) => $payment->memorizer?->clearPaymentCache());
    }

    public function scopeOfType(Builder $query, PaymentType $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeMonthly(Builder $query): Builder
    {
        return $query->ofType(PaymentType::MONTHLY);
    }

    public function memorizer(): BelongsTo
    {
        return $this->belongsTo(Memorizer::class);
    }
}
