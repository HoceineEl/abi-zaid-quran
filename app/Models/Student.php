<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class Student extends Model
{
    use HasFactory;

    protected $casts = [
        'with_reason' => 'boolean',
    ];

    public function progresses(): HasMany
    {
        return $this->hasMany(Progress::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function disconnections(): HasMany
    {
        return $this->hasMany(StudentDisconnection::class);
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
                    if ($progress->status === 'absent' && (int)$progress->with_reason === 0) {
                        $currentConsecutive++;
                    } else if ($progress->status === 'memorized' || ($progress->status === 'absent' && (int)$progress->with_reason === 1)) {
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
                // Use the existing method that properly checks CONSECUTIVE absent days
                $consecutiveAbsentDays = $this->getCurrentConsecutiveAbsentDays();

                if ($consecutiveAbsentDays >= 3) {
                    return 'critical';
                } elseif ($consecutiveAbsentDays >= 2) {
                    return 'warning';
                } else {
                    return 'normal';
                }
            }
        );
    }

    public function getAbsenceAttribute(): int
    {
        return $this->progresses()
            ->where('status', 'absent')
            ->where(function ($query) {
                $query->where('with_reason', 0)
                    ->orWhereNull('with_reason');
            })
            ->count();
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
                $recentProgresses = $this->progresses()
                    ->latest('date')
                    ->limit(30)
                    ->get();

                $absenceCount = $recentProgresses->where('status', 'absent')
                    ->where(function ($item) {
                        return (int)$item->with_reason === 0;
                    })
                    ->count();

                if ($absenceCount === 0) {
                    return ['label' => 'ممتاز', 'days' => $absenceCount, 'color' => '#28a745'];
                } elseif ($absenceCount >= 1 && $absenceCount <= 3) {
                    return ['label' => 'جيد', 'days' => $absenceCount, 'color' => '#5cb85c'];
                } elseif ($absenceCount >= 4 && $absenceCount <= 5) {
                    return ['label' => 'حسن', 'days' => $absenceCount, 'color' => '#8fd19e'];
                } elseif ($absenceCount >= 6 && $absenceCount <= 7) {
                    return ['label' => 'لا بأس به', 'days' => $absenceCount, 'color' => '#ffc107'];
                } elseif ($absenceCount >= 8 && $absenceCount <= 10) {
                    return ['label' => 'متوسط', 'days' => $absenceCount, 'color' => '#fd7e14'];
                } else {
                    return ['label' => 'ضعيف', 'days' => $absenceCount, 'color' => '#dc3545'];
                }
            }
        );
    }

    public function absentWithReasonToday(): Attribute
    {
        return Attribute::make(
            get: function () {
                return $this->today_progress()->where('with_reason', 1)->count();
            }
        );
    }

    public function getDisconnectionDateAttribute(): ?string
    {
        // Get the last day the student was present (memorized)
        $lastPresentDay = $this->progresses()
            ->where('status', 'memorized')
            ->latest('date')
            ->first();

        if (!$lastPresentDay) {
            return null;
        }

        // Calculate disconnection date as the day after the last present day
        return \Carbon\Carbon::parse($lastPresentDay->date)->addDay()->format('Y-m-d');
    }

    public function getDaysSinceLastPresentAttribute(): ?int
    {
        $lastPresentDate = $this->getLastPresentDate();

        if (!$lastPresentDate) {
            return null;
        }

        return (int) now()->diffInDays($lastPresentDate);
    }

    public function scopeDisconnectedFromActiveGroups(Builder $query, ?int $consecutiveDays = null): Builder
    {
        $consecutiveDays = $consecutiveDays ?? config('students.disconnection.consecutive_absent_days_threshold', 3);

        return $query->whereHas('group', function ($groupQuery) {
            $groupQuery->active();
        })->filter(function ($student) use ($consecutiveDays) {
            return $student->hasConsecutiveAbsentDaysInWorkingGroup($consecutiveDays);
        });
    }

    public function hasConsecutiveAbsentDaysInWorkingGroup(?int $requiredDays = null): bool
    {
        $requiredDays = $requiredDays ?? config('students.disconnection.consecutive_absent_days_threshold', 3);

        // Use the new method to get current consecutive absent days
        return $this->getCurrentConsecutiveAbsentDays() >= $requiredDays;
    }

    public function getGroupWorkingDates(int $limitDays = 30)
    {
        $startDate = Carbon::now()->subDays($limitDays)->format('Y-m-d');
        $endDate = Carbon::now()->format('Y-m-d');

        return Progress::where('date', '>=', $startDate)
            ->where('date', '<=', $endDate)
            ->whereHas('student', function ($query) {
                $query->where('group_id', $this->group_id);
            })
            ->distinct('date')
            ->orderBy('date', 'desc')
            ->pluck('date');
    }

    public function getDisconnectionDateBasedOnGroupActivity(): ?string
    {
        $threshold = config('students.disconnection.consecutive_absent_days_threshold', 3);

        // Only consider students with at least the configured consecutive absent days
        if ($this->getCurrentConsecutiveAbsentDays() < $threshold) {
            return null;
        }

        // Get the last present date
        $lastPresentDate = $this->getLastPresentDate();

        if ($lastPresentDate) {
            // Disconnection date is the day after last present
            return Carbon::parse($lastPresentDate)->addDay()->format('Y-m-d');
        }

        // If never present, use the date of the threshold consecutive absence
        // Get progress records from last 15 days (today - 14 days to today)
        $startDate = now()->subDays(14)->format('Y-m-d');
        $endDate = now()->format('Y-m-d');

        $recentProgresses = $this->progresses()
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('date', 'desc')
            ->get();

        $absentCount = 0;

        foreach ($recentProgresses as $progress) {
            // Skip null status records (WhatsApp reminder sent, pending)
            if ($progress->status === null) {
                continue;
            }

            // Only count explicit absence records without reason
            if ($progress->status === 'absent' && (int)$progress->with_reason === 0) {
                $absentCount++;
                if ($absentCount === $threshold) {
                    return $progress->date;
                }
            } else {
                // Stop when we find memorized or absent with reason
                break;
            }
        }

        return null;
    }

    public function getLastPresentDate(): ?string
    {
        // Get the working dates for the student's current group
        $groupWorkingDates = $this->getGroupWorkingDates(90); // Check last 90 days for better coverage

        if ($groupWorkingDates->isEmpty()) {
            return null;
        }

        // Find the last date where the student was present (memorized)
        // on a day when their current group was working
        foreach ($groupWorkingDates as $date) {
            $progress = $this->progresses()
                ->where('date', $date)
                ->where('status', 'memorized')
                ->first();

            if ($progress) {
                return $date;
            }
        }

        return null;
    }

    public function getLastPresentDateAttribute(): ?string
    {
        return $this->getLastPresentDate();
    }

    public function getCurrentConsecutiveAbsentDays(): int
    {
        // Get date range: last 15 days (today - 14 days to today)
        $startDate = now()->subDays(14)->format('Y-m-d');
        $endDate = now()->format('Y-m-d');

        // Get progress records within the last 15 days, ordered by date DESC
        $recentProgresses = $this->progresses()
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('date', 'desc')
            ->get();

        if ($recentProgresses->isEmpty()) {
            return 0;
        }

        $consecutiveAbsentDays = 0;

        // Check from most recent date backwards
        foreach ($recentProgresses as $progress) {
            // Skip null status records (WhatsApp reminder sent, pending) - continue counting
            if ($progress->status === null) {
                continue;
            }

            // Only count explicit absence records without reason
            if ($progress->status === 'absent' && (int)$progress->with_reason === 0) {
                $consecutiveAbsentDays++;
            } else {
                // Stop counting when we find a present day (memorized status) or absent with reason
                break;
            }
        }

        return $consecutiveAbsentDays;
    }

    public function getCurrentConsecutiveAbsentDaysAttribute(): int
    {
        return $this->getCurrentConsecutiveAbsentDays();
    }
}
