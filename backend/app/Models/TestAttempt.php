<?php

namespace App\Models;

use App\Enums\AttemptStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class TestAttempt extends Model
{
    protected $fillable = [
        'user_id', 'test_id', 'attempt_no', 'status', 'started_at', 'deadline_at', 'submitted_at',
        'time_taken_seconds', 'correct', 'incorrect', 'skipped', 'score',
    ];

    protected function casts(): array
    {
        return [
            'status' => AttemptStatus::class,
            'started_at' => 'datetime',
            'deadline_at' => 'datetime',
            'submitted_at' => 'datetime',
            'score' => 'float',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (TestAttempt $attempt) => $attempt->uuid ??= (string) Str::uuid());
    }

    /** URLs use the uuid, not the numeric id. */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function scopeReal($query)
    {
        return $query->whereIn('user_id', User::query()->real()->select('id'));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function test(): BelongsTo
    {
        return $this->belongsTo(Test::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(AttemptAnswer::class);
    }

    public function isFinished(): bool
    {
        return $this->status !== AttemptStatus::InProgress;
    }
}
