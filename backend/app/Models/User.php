<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * A STUDENT. Logs in with email + a one-time code (or phone + OTP when LMS_LOGIN_METHOD=phone); each login creates a
 * Sanctum token (one per device).
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'wordpress_user_id',
        'phone',
        'name',
        'nickname',
        'email',
        'email_verified_at',
        'target_exam',
        'attempt_year',
        'profile_completed_at',
        'is_active',
    ];

    protected $hidden = ['remember_token'];

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return [
            'attempt_year' => 'integer',
            'email_verified_at' => 'datetime',
            'profile_completed_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'enrollments')
            ->withPivot(['assigned_at', 'assigned_by'])
            ->withTimestamps();
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(TestAttempt::class);
    }

    /** What identifies the student on screen: their login (email, or phone when logging in by SMS). */
    public function loginId(): string
    {
        return (string) (config('lms.login_method') === 'phone' ? ($this->phone ?: $this->email) : ($this->email ?: $this->phone));
    }

    public function needsProfile(): bool
    {
        return $this->profile_completed_at === null;
    }

    /** Where email notifications go. */
    public function routeNotificationForMail(): ?string
    {
        return $this->email;
    }
}
