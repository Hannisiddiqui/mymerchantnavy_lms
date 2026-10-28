<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A course assigned to or purchased by a student. */
class Enrollment extends Model
{
    protected $fillable = [
        'user_id',
        'course_id',
        'assigned_by',
        'assigned_at',
        'purchase_date',
        'expires_at',
        'purchase_type',
        'order_id',
        'payment_id',
        'amount',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'purchase_date' => 'datetime',
            'expires_at' => 'datetime',
            'amount' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'assigned_by');
    }

    /** Check if the enrollment course access is expired */
    public function isExpired(): bool
    {
        if (empty($this->expires_at)) {
            return false;
        }

        $expiry = $this->expires_at instanceof Carbon ? $this->expires_at : Carbon::parse($this->expires_at);

        return $expiry->isPast();
    }

    /** Check if the enrollment course access is currently active */
    public function isActive(): bool
    {
        if ($this->status === 'cancelled') {
            return false;
        }

        return ! $this->isExpired();
    }

    /** Return human-friendly remaining duration or expiry status */
    public function remainingDuration(): ?string
    {
        if (! $this->expires_at) {
            return 'Lifetime Access';
        }

        if ($this->isExpired()) {
            return 'Expired on ' . $this->expires_at->format('d M Y');
        }

        $now = now();
        $diff = $now->diff($this->expires_at);

        $parts = [];
        if ($diff->y > 0) {
            $parts[] = $diff->y . ' ' . ($diff->y === 1 ? 'year' : 'years');
        }
        if ($diff->m > 0) {
            $parts[] = $diff->m . ' ' . ($diff->m === 1 ? 'month' : 'months');
        }
        if ($diff->d > 0 && $diff->y === 0) {
            $parts[] = $diff->d . ' ' . ($diff->d === 1 ? 'day' : 'days');
        }

        return ! empty($parts) ? implode(' ', $parts) . ' left' : 'Expires today';
    }

    /** Scopes */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', '!=', 'cancelled')
            ->where(function (Builder $q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->whereNotNull('expires_at')
            ->where('expires_at', '<=', now());
    }

    public function scopePaid(Builder $query): Builder
    {
        return $query->where('purchase_type', 'paid');
    }

    public function scopeFree(Builder $query): Builder
    {
        return $query->where('purchase_type', 'free');
    }
}
