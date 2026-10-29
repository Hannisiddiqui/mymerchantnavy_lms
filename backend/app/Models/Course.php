<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    /** Deleting a course deletes its tests one by one, so each test also removes the questions only it used. */
    protected static function booted(): void
    {
        static::deleting(fn (Course $course) => $course->tests()->get()->each->delete());
    }

    protected $fillable = [
        'title',
        'slug',
        'wordpress_product_id',
        'description',
        'thumbnail_path',
        'is_active',
        'validity_type',
        'validity_duration_months',
        'validity_duration_days',
        'validity_fixed_expiry_date',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'validity_duration_months' => 'integer',
            'validity_duration_days' => 'integer',
            'validity_fixed_expiry_date' => 'date',
        ];
    }

    /** Calculate the exact expiry timestamp based on purchase date and course validity rules. */
    public function calculateExpiry(?\DateTimeInterface $purchaseDate = null): ?Carbon
    {
        $start = $purchaseDate ? Carbon::parse($purchaseDate) : now();
        $type = $this->validity_type ?: '6_months';

        return match ($type) {
            '6_months' => $start->copy()->addMonthsNoOverflow(6),
            '1_year' => $start->copy()->addYearsNoOverflow(1),
            'custom' => match (true) {
                filled($this->validity_fixed_expiry_date) => Carbon::parse($this->validity_fixed_expiry_date)->endOfDay(),
                filled($this->validity_duration_months) && $this->validity_duration_months > 0 => $start->copy()->addMonthsNoOverflow((int) $this->validity_duration_months),
                filled($this->validity_duration_days) && $this->validity_duration_days > 0 => $start->copy()->addDays((int) $this->validity_duration_days),
                default => $start->copy()->addMonthsNoOverflow(6),
            },
            'lifetime' => null,
            default => $start->copy()->addMonthsNoOverflow(6),
        };
    }

    /** Subjects and mock tests, in display order. */
    public function curriculum(): HasMany
    {
        return $this->hasMany(CurriculumItem::class)->orderBy('position');
    }

    public function subjects(): HasMany
    {
        return $this->hasMany(Subject::class);
    }

    public function tests(): HasMany
    {
        return $this->hasMany(Test::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'enrollments')
            ->withPivot(['assigned_at', 'assigned_by'])
            ->withTimestamps();
    }
}
