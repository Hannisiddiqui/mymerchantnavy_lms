<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A discount code. Applied by the LMS when WordPress asks for the price of a product (see CouponService). */
class Coupon extends Model
{
    protected $fillable = [
        'code', 'description', 'type', 'value', 'max_discount', 'min_amount', 'product_ids',
        'usage_limit', 'per_student_limit', 'used_count', 'starts_at', 'expires_at', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'max_discount' => 'decimal:2',
            'min_amount' => 'decimal:2',
            'product_ids' => 'array',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(fn (self $c) => $c->code = strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', (string) $c->code)));
    }

    public function statusLabel(): string
    {
        return match (true) {
            ! $this->is_active => 'Paused',
            $this->expires_at && $this->expires_at->isPast() => 'Expired',
            $this->starts_at && $this->starts_at->isFuture() => 'Scheduled',
            $this->usage_limit && $this->used_count >= $this->usage_limit => 'Used up',
            default => 'Live',
        };
    }
}
