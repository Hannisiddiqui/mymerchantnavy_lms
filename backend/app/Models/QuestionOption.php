<?php

namespace App\Models;

use App\Models\Concerns\SanitizesHtml;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionOption extends Model
{
    use SanitizesHtml;

    /** HTML columns that are cleaned on save. */
    protected array $htmlFields = ['body'];

    protected $fillable = ['question_id', 'position', 'body', 'is_correct'];

    // NOTE: `is_correct` must stay visible here (the admin editor reads it).
    // Student-facing API Resources must never include it until the attempt is submitted.

    protected function casts(): array
    {
        return ['is_correct' => 'boolean'];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
