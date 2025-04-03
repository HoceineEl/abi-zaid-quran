<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;

class Student extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'phone',
        'group',
        'sex',
        'city',
        'group_id',
    ];

    public function progresses(): HasMany
    {
        return $this->hasMany(Progress::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function getProgressAttribute(): float
    {
        $page = $this->progresses->last()->page->number ?? 0;
        $progress = $page * 100 / 604;

        return round($progress, 2);
    }

    public function needsCall(): bool
    {
        $recentProgresses = $this->progresses()->latest()->limit(2)->get();

        $absentCount = $recentProgresses->where('status', 'absent')->count();

        return $absentCount >= 2;
    }
    public function consecutiveAbsentDays(): Attribute
    {
        return Attribute::make(
            get: function () {
                $recentProgresses = $this->progresses()
                    ->latest('date')
                    ->limit(30)
                    ->orderBy('date', 'asc')
                    ->get();

                $currentConsecutive = 0;

                foreach ($recentProgresses as $progress) {
                    if ($progress->status === 'absent') {
                        $currentConsecutive++;
                    } else if ($progress->status === 'memorized') {
                        $currentConsecutive = 0;
                    }
                }

                return $currentConsecutive;
            }
        );
    }

    public function absenceStatus(): Attribute
    {
        return Attribute::make(
            get: function () {
                $absentProgresses = $this->progresses()
                    ->latest('date')
                    ->limit(3)
                    ->orderBy('date', 'asc')
                    ->get();
                $threeDays = $absentProgresses;
                $twoDays = $absentProgresses->take(2);

                $twoDaysEbsentCount = $twoDays->where('status', 'absent')->count();
                $threeDaysEbsentCount = $threeDays->where('status', 'absent')->count();

                if (
                    $threeDaysEbsentCount >= 3
                ) {
                    return 'critical';
                } elseif (
                    $twoDaysEbsentCount >= 2
                ) {
                    return 'warning';
                } else {
                    return 'normal';
                }
            }
        );
    }

    public function getAbsenceAttribute(): int
    {
        return $this->progresses()->where('status', 'absent')->count();
    }

    public function today_progress()
    {
        return $this->hasOne(Progress::class)
            ->where('date', now()->format('Y-m-d'))
            ->latest();
    }

    public function needACall(): Attribute
    {
        return Attribute::make(
            get: function () {
                $recentProgresses = $this->progresses()->latest()->limit(3)->get();
                $absentCount = $recentProgresses->where('status', 'absent')->count();
                return $absentCount >= 3;
            }
        );
    }

    protected static function booted()
    {
        static::addGlobalScope('ordered', function ($query) {
            $query->orderBy('order_no');
        });
    }

    public function attendanceRemark(): Attribute
    {
        return Attribute::make(
            get: function () {
                // Get the recent progress records for the last 30 days
                $recentProgresses = $this->progresses()
                    ->latest('date')
                    ->limit(30)
                    ->get();

                // Count absences in the last 30 days
                $absenceCount = $recentProgresses->where('status', 'absent')->count();

                // Return appropriate remark based on absence count
                if ($absenceCount === 0) {
                    return ['label' => 'ممتاز', 'days' => $absenceCount, 'color' => '#28a745']; // Green
                } elseif ($absenceCount >= 1 && $absenceCount <= 3) {
                    return ['label' => 'جيد', 'days' => $absenceCount, 'color' => '#5cb85c']; // Light Green
                } elseif ($absenceCount >= 4 && $absenceCount <= 5) {
                    return ['label' => 'حسن', 'days' => $absenceCount, 'color' => '#8fd19e']; // Lighter Green
                } elseif ($absenceCount >= 6 && $absenceCount <= 7) {
                    return ['label' => 'لا بأس به', 'days' => $absenceCount, 'color' => '#ffc107']; // Yellow
                } elseif ($absenceCount >= 8 && $absenceCount <= 10) {
                    return ['label' => 'متوسط', 'days' => $absenceCount, 'color' => '#fd7e14']; // Orange
                } else {
                    return ['label' => 'ضعيف', 'days' => $absenceCount, 'color' => '#dc3545']; // Red
                }
            }
        );
    }
}
