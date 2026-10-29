<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Batch extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'batch_code',
        'target_exam',
        'image_url',
        'description',
        'highlights',
        'mrp_price',
        'price',
        'discount_percent',
        'starts_at',
        'validity_text',
        'ends_at',
        'purchase_url',
        'wp_product_id',
        'footer_tag',
        'is_live',
        'status',
        'sort_order',
    ];

    protected $casts = [
        'highlights' => 'array',
        'is_live' => 'boolean',
        'starts_at' => 'date',
        'ends_at' => 'date',
        'mrp_price' => 'decimal:2',
        'price' => 'decimal:2',
        'discount_percent' => 'integer',
        'sort_order' => 'integer',
    ];

    /**
     * Included courses bundle.
     */
    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'batch_course')->withTimestamps();
    }

    /**
     * Included mock tests bundle.
     */
    public function tests(): BelongsToMany
    {
        return $this->belongsToMany(Test::class, 'batch_test')->withTimestamps();
    }

    /**
     * Directly enrolled students.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'batch_user')
            ->withPivot('enrolled_at', 'expires_at')
            ->withTimestamps();
    }

    /**
     * Calculated discount percentage if not manually specified.
     */
    public function getEffectiveDiscountAttribute(): int
    {
        if ($this->discount_percent) {
            return (int) $this->discount_percent;
        }

        if ($this->mrp_price && $this->mrp_price > $this->price && $this->mrp_price > 0) {
            return (int) round((($this->mrp_price - $this->price) / $this->mrp_price) * 100);
        }

        return 0;
    }

    /**
     * Check if a specific user has active access to this batch.
     */
    public function isEnrolledByUser(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        // Direct batch enrollment
        $hasBatch = $this->users()->where('users.id', $user->id)->exists();
        if ($hasBatch) {
            return true;
        }

        // Access via any included course enrollment
        $courseIds = $this->courses()->pluck('courses.id');
        if ($courseIds->isNotEmpty()) {
            return $user->enrollments()
                ->whereIn('course_id', $courseIds)
                ->where('status', 'active')
                ->where(function ($q) {
                    $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->exists();
        }

        return false;
    }
}

