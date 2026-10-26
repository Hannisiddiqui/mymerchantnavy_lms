<?php

namespace App\Models;

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

    protected $fillable = ['title', 'slug', 'wordpress_product_id', 'description', 'thumbnail_path', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
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

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'enrollments')
            ->withPivot(['assigned_at', 'assigned_by'])
            ->withTimestamps();
    }
}
