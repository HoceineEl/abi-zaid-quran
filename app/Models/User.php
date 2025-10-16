<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'role',
        'sex',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'restudent_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'association') {
            return str_ends_with($this->email, '@association.com') && $this->isAdministrator();
        }
        if ($panel->getId() === 'teacher') {
            return $this->isTeacher();
        } elseif ($panel->getId() === 'quran-program') {
            return ! $this->isTeacher() && ! $this->hasAssociationAccess();
        }

        return true;
    }

    public function hasAssociationAccess(): bool
    {
        return str_ends_with($this->email, '@association.com') && $this->isAdministrator();
    }

    public function managedGroups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'group_manager', 'manager_id', 'group_id');
    }

    public function isAdministrator(): bool
    {
        return $this->role === 'admin';
    }

    public function progresses(): HasMany
    {
        return $this->hasMany(Progress::class, 'created_by');
    }

    public function scopeCreatedProgressesToday($query)
    {
        return $query->whereHas('progresses', function ($query) {
            $query->whereDate('created_at', today());
        });
    }

    public function memorizers(): HasMany
    {
        return $this->hasMany(Memorizer::class, 'teacher_id');
    }

    public function isTeacher(): bool
    {
        return $this->role === 'teacher';
    }

    public function reminderLogs(): HasMany
    {
        return $this->hasMany(ReminderLog::class, 'created_by');
    }

    public function attendanceLogs(): HasMany
    {
        return $this->hasMany(Attendance::class, 'created_by');
    }

    public function memoGroups(): HasMany
    {
        return $this->hasMany(MemoGroup::class, 'teacher_id');
    }
}
