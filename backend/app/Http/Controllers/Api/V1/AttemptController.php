<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveAnswerRequest;
use App\Http\Requests\SubmitAttemptRequest;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Services\Tests\AttemptService;
use App\Services\Tests\ResultPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Taking a test: start/resume, save answers, submit, read the result. Rules live in AttemptService. */
class AttemptController extends Controller
{
    public function __construct(private AttemptService $attempts, private ResultPresenter $results) {}

    /** POST /api/v1/tests/{test}/attempts — starts a new attempt or resumes the unfinished one */
    public function start(Request $request, int $test): JsonResponse
    {
        $catalog = $this->catalog($request);
        $test = $catalog->findTest($test);
        $attempt = $this->attempts->start($request->user(), $test);

        \App\Models\ActivityLog::record(
            event: 'test.attempt_started',
            description: "Student {$request->user()->email} started attempt on test '{$test->title}'",
            subject: $attempt,
            properties: ['test_id' => $test->id, 'attempt_uuid' => $attempt->uuid]
        );

        return $this->data($this->session($attempt, $test, $catalog->testSummary($test)));
    }

    /** PUT /api/v1/attempts/{attempt}/answers/{question} */
    public function saveAnswer(SaveAnswerRequest $request, string $attempt, int $question): JsonResponse
    {
        $this->attempts->saveAnswer($this->own($request, $attempt), $question, $request->validated());

        return $this->data(null);
    }

    /** POST /api/v1/attempts/{attempt}/submit */
    public function submit(SubmitAttemptRequest $request, string $attempt): JsonResponse
    {
        $done = $this->attempts->submit($this->own($request, $attempt), $request->validated('answers'));
        $this->catalog($request)->refresh();

        \App\Models\ActivityLog::record(
            event: 'test.attempt_submitted',
            description: "Student {$request->user()->email} submitted test '{$done->test?->title}' (Score: {$done->score}/{$done->max_score})",
            subject: $done,
            properties: [
                'test_id' => $done->test_id,
                'score' => $done->score,
                'max_score' => $done->max_score,
                'attempt_uuid' => $done->uuid,
            ]
        );

        return $this->data(['attemptId' => $done->uuid]);
    }

    /** GET /api/v1/attempts/{attempt}/result — only after the attempt is finished */
    public function result(Request $request, string $attempt): JsonResponse
    {
        $attempt = $this->own($request, $attempt);
        abort_unless($attempt->isFinished(), 404);

        return $this->data($this->results->full($attempt->load('test.course'), $this->catalog($request)));
    }

    /** GET /api/v1/results — every finished attempt, newest first */
    public function index(Request $request): JsonResponse
    {
        $attempts = $request->user()->attempts()->whereNotNull('submitted_at')
            ->with('test.course')->latest('submitted_at')->get();

        return $this->data($attempts->map(fn (TestAttempt $a) => $this->results->summary($a))->values()->all());
    }

    // ------------------------------------------------------------------

    /** The student's own attempt (by public uuid); other students' attempts are "not found". */
    private function own(Request $request, string $uuid): TestAttempt
    {
        return $request->user()->attempts()->where('uuid', $uuid)->with('test')->firstOrFail();
    }

    /** Matches `AttemptSession` in student-app/src/lib/api/tests.ts. Correct answers are never included. */
    private function session(TestAttempt $attempt, Test $test, array $summary): array
    {
        $questions = $test->questions()->with('options')->get()->map(fn ($q) => [
            'id' => $q->id,
            'body' => $q->body,
            'options' => $q->options->map(fn ($o) => ['id' => $o->id, 'label' => (string) $o->position, 'body' => $o->body])->all(),
        ])->all();

        $answers = $attempt->answers()->get()->mapWithKeys(fn ($a) => [$a->question_id => [
            'selectedOptionId' => $a->selected_option_id,
            'markedForReview' => $a->marked_for_review,
            'visited' => $a->visited,
        ]])->all();

        return [
            'attemptId' => $attempt->uuid,
            'startedAt' => $attempt->started_at->toIso8601String(),
            'deadline' => $attempt->deadline_at->toIso8601String(),
            'test' => $summary + ['questions' => $questions],
            'answers' => (object) $answers,
        ];
    }
}
