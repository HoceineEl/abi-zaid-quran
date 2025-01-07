<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class Memorizer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'phone',
        'address',
        'birth_date',
        'sex',
        'city',
        'photo',
        'exempt',
        'memo_group_id',
        'teacher_id',
        'guardian_id',
        'round_id',
    ];

    protected $appends = [
        'number',
    ];

    public function number(): Attribute
    {
        return Attribute::make(
            get: function () {
                $date = $this->created_at ?? now();
                return sprintf(
                    '%s%s%s%02d',
                    $date->format('y'),
                    $date->format('m'),
                    $date->format('d'),
                    $this->id
                );
            },
        );
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function reminderLogs(): HasMany
    {
        return $this->hasMany(ReminderLog::class);
    }

    public function hasPaymentThisMonth(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->payments()->whereMonth('payment_date', now()->month)->exists() || $this->exempt,
        );
    }


    public function hasReminderThisMonth(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->reminderLogs()->whereMonth('created_at', now()->month)->exists(),
        );
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(MemoGroup::class, 'memo_group_id');
    }

    public function getPhotoUrlAttribute(): string
    {
        return $this->photo ? asset($this->photo) : ($this->sex == 'male' ? asset('images/default-boy.png') : asset('images/default-girl.png'));
    }

    public function presentToday(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->attendances()->whereDate('date', now()->toDateString())->whereNotNull('check_in_time')->exists(),
        );
    }

    public function absentToday(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->attendances()->whereDate('date', now()->toDateString())->whereNull('check_in_time')->exists(),
        );
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function teacher(): HasOneThrough
    {
        return $this->hasOneThrough(User::class, MemoGroup::class, 'id', 'id', 'memo_group_id', 'teacher_id');
    }

    public function sex(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->group?->teacher?->sex ?? 'male',
        );
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class);
    }


    public function getMessageToSend($messageType): string
    {
        $studentPrefix = $this->sex === 'male' ? "الطالب" : "الطالبة";
        $arabicDate = now()->translatedFormat('l j F Y');
        return match ($messageType) {
            'absence' => "السلام عليكم ورحمه الله وبركاته،\n" .
                "تخبركم إدارة جمعية بن ابي زيد القيرواني أن {$studentPrefix}: {$this->name} قد تغيب عن حصة اليوم.\n" .
                "لذلك المرجو منكم تبرير هذا الغياب\n" .
                "كما نخبركم أنه عملا بالقانون الداخلي للجمعية فإن أربعة غيابات بدون مبرر في الشهر تعرض ابنكم/ ابنتكم للفصل.\n\n" .
                "اسم {$studentPrefix}: {$this->name}\n" .
                "التاريخ: {$arabicDate}",

            'trouble' => "السلام عليكم ورحمه الله وبركاته،\n" .
                "تخبركم إدارة جمعية بن ابي زيد القرواني أن ابنكم قد سجلت عليه حاله شغب في الحلقة اليوم.\n" .
                "وكما لا يخفى عليكم فان الشغب يؤثر سلبا على حلقة القرآن.\n" .
                "لذلك و عملا بالقانون الداخلي للجمعية فإن كل طالب توصل بثلاثة إنذارات بالشغب يعرض نفسه للفصل من الجمعية في المرة الرابعة.\n\n" .
                "اسم {$studentPrefix}: {$this->name}\n" .
                "التاريخ: {$arabicDate}",

            'no_memorization' => "السلام عليكم ورحمه الله وبركاته،\n" .
                "تخبركم إداره جمعيه بن ابي زيد القرواني أن ابنكم قد سجلت عليه تكرار عدم الحفظ\n" .
                "وكما لا يخفى عليكم فإن تكرار عدم الحفظ يؤثر سلبا على مردودية الطالب في الحلقة.\n" .
                "لذلك نهيب بكم مراقبة دفتر التواصل الخاص بابنكم و الحرص على برمجة أوقات للحفظ في البيت.\n" .
                "لذلك و عملا بالقانون الداخلي للجمعية فإن تكرار عدم الحفظ يعرض الطالب للفصل من الجمعية.\n\n" .
                "اسم {$studentPrefix}: {$this->name}\n" .
                "التاريخ: {$arabicDate}",

            default => ''
        };
    }

    public function round(): BelongsTo
    {
        return $this->belongsTo(Round::class);
    }

    protected function displayPhone(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->phone ?: $this->guardian?->phone,
        );
    }

    public function todayScore(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->attendances()->whereDate('date', now()->toDateString())->first()?->score,
        );
    }

    public function todayAttendance(): HasOne
    {
        return $this->hasOne(Attendance::class)->whereDate('date', now()->toDateString());
    }

    public function troubles(): Attribute
    {
        return Attribute::make(
            get: function () {
                $troubles = [];
                foreach ($this->attendances as $attendance) {
                    if ($attendance->notes) {
                        $troubles = array_merge($troubles, $attendance->notes);
                    }
                }
                return array_unique($troubles);
            }
        );
    }
}
