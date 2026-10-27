<?php

namespace App\Services\Tests;

use App\Models\QuestionReport;
use App\Models\TestAttempt;
use App\Services\Student\StudentCatalog;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Builds the result JSON (matches AttemptSummary / AttemptResult in student-app/src/types/index.ts).
 * Correct answers and solutions are only included for FINISHED attempts.
 */
class ResultPresenter
{
    public function __construct(private RankingService $ranking) {}

    public function summary(TestAttempt $attempt): array
    {
        $test = $attempt->test;
        $questions = $test->questions()->count();
        $attempted = $attempt->correct + $attempt->incorrect;

        return [
            'id' => $attempt->uuid,
            'testId' => $test->id,
            'testTitle' => $test->title,
            'testKind' => $test->kind->value,
            'courseTitle' => $test->course->title,
            'attemptNo' => $attempt->attempt_no,
            'submittedAt' => $attempt->submitted_at->toIso8601String(),
            'score' => $attempt->score,
            'totalMarks' => $questions * $test->marks_correct,
            'accuracy' => $attempted ? (int) round($attempt->correct / $attempted * 100) : 0,
        ] + Arr::only($this->ranking->for($attempt), ['rank', 'totalCandidates']);
    }

    public function full(TestAttempt $attempt, StudentCatalog $catalog): array
    {
        $test = $attempt->test->loadMissing('course')->loadCount('questions');
        $ranking = $this->ranking->for($attempt);
        $answers = $attempt->answers()->get()->keyBy('question_id');
        // The student's own latest report on each question, so the "Report" button can show its status.
        $reports = QuestionReport::where('user_id', $attempt->user_id)
            ->whereIn('question_id', DB::table('test_questions')->where('test_id', $test->id)->select('question_id'))
            ->latest('id')->get()->unique('question_id')->keyBy('question_id');

        $solutions = $test->questions()->with('options')->get()->map(function ($q) use ($answers, $reports) {
            return [
                'id' => $q->id,
                'body' => $q->body,
                'options' => $q->options->map(fn ($o) => ['id' => $o->id, 'label' => (string) $o->position, 'body' => $o->body])->all(),
                'correctOptionId' => $q->options->firstWhere('is_correct', true)?->id,
                'selectedOptionId' => $answers->get($q->id)?->selected_option_id,
                'explanation' => $q->explanation ?? '',
                'report' => $reports->get($q->id)?->toStudentArray(),
            ];
        })->values()->all();

        return $this->summary($attempt) + [
            'test' => $catalog->testSummary($test),
            'timeTakenSeconds' => $attempt->time_taken_seconds ?? 0,
            'correct' => $attempt->correct,
            'incorrect' => $attempt->incorrect,
            'skipped' => $attempt->skipped,
            'marksObtained' => $attempt->correct * $test->marks_correct,
            'marksLost' => $attempt->incorrect * $test->marks_wrong,
            'percentile' => $ranking['percentile'],
            'attempts' => TestAttempt::where('user_id', $attempt->user_id)->where('test_id', $test->id)
                ->whereNotNull('submitted_at')->orderBy('attempt_no')->get(['uuid', 'attempt_no'])
                ->map(fn ($a) => ['id' => $a->uuid, 'attemptNo' => $a->attempt_no])->all(),
            'solutions' => $solutions,
        ];
    }
}
