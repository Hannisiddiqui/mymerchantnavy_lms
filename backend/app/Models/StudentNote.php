<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One of a student's own notes on a piece of content. Private to that student. */
class StudentNote extends Model
{
    protected $fillable = ['user_id', 'chapter_content_id', 'page', 'body'];

    protected function casts(): array
    {
        return ['page' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function content(): BelongsTo
    {
        return $this->belongsTo(ChapterContent::class, 'chapter_content_id');
    }
}
