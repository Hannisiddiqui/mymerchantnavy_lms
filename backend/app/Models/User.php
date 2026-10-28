<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
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

    protected static function booted(): void
    {
        // Switching a student to inactive also ends every session they have open, so the block takes effect at once.
        static::updated(function (self $student) {
            if ($student->wasChanged('is_active') && ! $student->is_active) {
                $student->tokens()->delete();
            }
        });
    }

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'enrollments')
            ->withPivot([
                'assigned_at',
                'assigned_by',
                'purchase_date',
                'expires_at',
                'purchase_type',
                'order_id',
                'payment_id',
                'amount',
                'status',
            ])
            ->withTimestamps();
    }

    public function activeCourses(): BelongsToMany
    {
        return $this->courses()
            ->where(function (Builder $q) {
                $q->whereNull('enrollments.expires_at')
                    ->orWhere('enrollments.expires_at', '>', now());
            })
            ->where('enrollments.status', '!=', 'cancelled');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function activeEnrollments(): HasMany
    {
        return $this->enrollments()->active();
    }

    public function expiredEnrollments(): HasMany
    {
        return $this->enrollments()->expired();
    }

    /** Query scopes for filtering students */
    public function scopeWithPaidEnrollment(Builder $query): Builder
    {
        return $query->whereHas('enrollments', fn (Builder $q) => $q->where('purchase_type', 'paid'));
    }

    public function scopeWithFreeEnrollment(Builder $query): Builder
    {
        return $query->whereHas('enrollments', fn (Builder $q) => $q->where('purchase_type', 'free'));
    }

    public function scopeWithoutCourses(Builder $query): Builder
    {
        return $query->whereDoesntHave('enrollments');
    }

    public function scopeWithActiveEnrollment(Builder $query): Builder
    {
        return $query->whereHas('enrollments', fn (Builder $q) => $q->active());
    }

    public function scopeWithExpiredEnrollment(Builder $query): Builder
    {
        return $query->whereHas('enrollments', fn (Builder $q) => $q->expired());
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
