<?php

namespace App\Models;

use App\Models\Concerns\SanitizesHtml;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Question-bank entry: 4-option single-correct MCQ. Body and explanation are HTML with LaTeX. */
class Question extends Model
{
    use SanitizesHtml;

    /** HTML columns that are cleaned on save. */
    protected array $htmlFields = ['body', 'explanation'];

    protected $fillable = ['subject_id', 'chapter_id', 'body', 'explanation', 'difficulty'];

    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class)->orderBy('position');
    }

    public function correctOption(): HasOne
    {
        return $this->hasOne(QuestionOption::class)->where('is_correct', true);
    }

    public function tests(): BelongsToMany
    {
        return $this->belongsToMany(Test::class, 'test_questions')->withPivot('position');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class);
    }
}
