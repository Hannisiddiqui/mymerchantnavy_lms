<?php

namespace App\Models;

use App\Enums\TestKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/** A practice test (inside a chapter) or a mock test (in the course curriculum). */
class Test extends Model
{
    protected $fillable = ['course_id', 'title', 'kind', 'duration_minutes', 'marks_correct', 'marks_wrong', 'instructions', 'is_published'];

    protected function casts(): array
    {
        return [
            'kind' => TestKind::class,
            'marks_correct' => 'float',
            'marks_wrong' => 'float',
            'is_published' => 'boolean',
        ];
    }

    /** Questions of a test being deleted, until the test row is gone (keyed by test id). */
    private static array $leaving = [];

    /**
     * Deleting a test also deletes the questions that belong ONLY to it. A question that another test uses is kept,
     * so deleting one test never empties another.
     */
    protected static function booted(): void
    {
        static::deleting(function (Test $test) {
            self::$leaving[$test->id] = DB::table('test_questions')->where('test_id', $test->id)->pluck('question_id')->all();
            DB::table('test_questions')->where('test_id', $test->id)->delete();
        });
        static::deleted(function (Test $test) {
            $ids = self::$leaving[$test->id] ?? [];
            unset(self::$leaving[$test->id]);
            if (! $ids) {
                return;
            }
            $stillUsed = DB::table('test_questions')->whereIn('question_id', $ids)->pluck('question_id')->all();
            Question::whereIn('id', array_diff($ids, $stillUsed))->get()->each->delete();
        });
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'test_questions')
            ->withPivot('position')
            ->orderByPivot('position');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(TestAttempt::class);
    }
}
