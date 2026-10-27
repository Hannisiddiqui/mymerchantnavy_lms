<?php

namespace App\Services\Student;

use App\Models\Chapter;
use App\Models\ChapterContent;
use App\Models\CurriculumItem;
use App\Models\Subject;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The student's performance dashboard: every submitted attempt, broken down by subject and chapter.
 *
 * A question counts towards the chapter it was written for (`questions.chapter_id`), so a mock test that mixes chapters
 * still feeds each chapter. Questions without a chapter count towards the chapter whose practice test they are in.
 * Every attempt counts (reattempts too), so the numbers grow with practice. Scores and "last score" come from the
 * chapter's own practice test.
 *
 * Matches `PerformanceReport` in student-app/src/types/index.ts.
 */
class PerformanceReport
{
    public function __construct(private User $user, private StudentCatalog $catalog) {}

    public function build(): array
    {
        $attempts = TestAttempt::with('test')
            ->where('user_id', $this->user->id)->whereNotNull('submitted_at')
            ->orderBy('submitted_at')->get()
            ->filter(fn (TestAttempt $a) => $a->test !== null);

        $totals = Test::withCount('questions')->whereIn('id', $attempts->pluck('test_id')->unique())->get()
            ->mapWithKeys(fn (Test $t) => [$t->id => $t->questions_count * $t->marks_correct]);
        $percent = fn (TestAttempt $a) => ($total = $totals->get($a->test_id)) ? round($a->score / $total * 100, 1) : 0.0;

        // test id => chapter it sits in as a practice test
        $testChapter = ChapterContent::where('contentable_type', (new Test)->getMorphClass())
            ->whereIn('contentable_id', Test::where('is_published', true)->select('id'))->pluck('chapter_id', 'contentable_id');
        $byChapter = $this->outcomesByChapter($testChapter);

        $subjects = $this->subjects()->map(function (Subject $subject) use ($attempts, $byChapter, $testChapter, $percent) {
            $chapters = $subject->chapters->map(function (Chapter $chapter) use ($attempts, $byChapter, $testChapter, $percent) {
                $o = $byChapter->get($chapter->id, ['correct' => 0, 'incorrect' => 0, 'skipped' => 0, 'attempts' => 0]);
                $testIds = $testChapter->filter(fn ($chapterId) => (int) $chapterId === $chapter->id)->keys();
                $own = $attempts->whereIn('test_id', $testIds);
                $last = $own->last();

                return [
                    'id' => $chapter->id,
                    'title' => $chapter->title,
                    'testId' => $testIds->first(),
                    ...$this->counts($o),
                    'attempts' => $o['attempts'],
                    'bestScorePercent' => $own->isEmpty() ? null : $own->map($percent)->max(),
                    'lastScorePercent' => $last ? $percent($last) : null,
                    'lastAttemptId' => $last?->uuid,
                    'lastAttemptAt' => $last?->submitted_at->toIso8601String(),
                ];
            })->values();

            $sum = fn (string $key) => $chapters->sum($key);
            $started = $chapters->filter(fn ($c) => $c['attempts'] > 0);

            return [
                'id' => $subject->id,
                'title' => $subject->title,
                'courseTitle' => $subject->course->title,
                'chaptersTotal' => $chapters->count(),
                'chaptersStarted' => $started->count(),
                ...$this->counts(['correct' => $sum('correct'), 'incorrect' => $sum('incorrect'), 'skipped' => $sum('skipped')]),
                'avgScorePercent' => $started->whereNotNull('lastScorePercent')->isEmpty() ? null
                    : round($started->whereNotNull('lastScorePercent')->avg('lastScorePercent'), 1),
                'chapters' => $chapters->all(),
            ];
        })->values();

        $correct = $attempts->sum('correct');
        $incorrect = $attempts->sum('incorrect');

        return [
            'overview' => [
                'testsTaken' => $attempts->pluck('test_id')->unique()->count(),
                'attempts' => $attempts->count(),
                ...$this->counts(['correct' => $correct, 'incorrect' => $incorrect, 'skipped' => $attempts->sum('skipped')]),
                'avgScorePercent' => $attempts->isEmpty() ? null : round($attempts->map($percent)->avg(), 1),
                'bestScorePercent' => $attempts->isEmpty() ? null : $attempts->map($percent)->max(),
                'timeSpentSeconds' => (int) $attempts->sum('time_taken_seconds'),
                'chaptersStarted' => $subjects->sum('chaptersStarted'),
                'chaptersTotal' => $subjects->sum('chaptersTotal'),
                'lastActivityAt' => $attempts->last()?->submitted_at->toIso8601String(),
            ],
            // Latest 20 attempts, oldest first, for the score trend chart
            'trend' => $attempts->slice(-20)->map(fn (TestAttempt $a) => [
                'attemptId' => $a->uuid,
                'testTitle' => $a->test->title,
                'testKind' => $a->test->kind->value,
                'submittedAt' => $a->submitted_at->toIso8601String(),
                'scorePercent' => $percent($a),
                'accuracy' => ($a->correct + $a->incorrect) ? (int) round($a->correct / ($a->correct + $a->incorrect) * 100) : 0,
            ])->values()->all(),
            'subjects' => $subjects->all(),
        ];
    }

    /** @param  array{correct:int,incorrect:int,skipped:int}  $o */
    private function counts(array $o): array
    {
        $answered = $o['correct'] + $o['incorrect'];

        return [
            'correct' => (int) $o['correct'],
            'incorrect' => (int) $o['incorrect'],
            'skipped' => (int) $o['skipped'],
            'accuracy' => $answered ? (int) round($o['correct'] / $answered * 100) : null,
        ];
    }

    /** Subjects of the student's courses, in course-page order, with their real chapters (not the mock-test entries). */
    private function subjects(): Collection
    {
        $order = CurriculumItem::whereIn('course_id', $this->catalog->courseIds())
            ->where('item_type', (new Subject)->getMorphClass())
            ->orderBy('course_id')->orderBy('position')->pluck('item_id');

        $subjects = Subject::with(['course', 'chapters' => fn ($q) => $q->realChapters()->orderBy('position')])
            ->whereIn('id', $order)->get()->keyBy('id');

        return $order->map(fn ($id) => $subjects->get($id))->filter()->values();
    }

    /**
     * chapter id => right / wrong / skipped answers over all the student's submitted attempts, and how many attempts
     * touched the chapter.
     */
    private function outcomesByChapter(Collection $testChapter): Collection
    {
        $rows = DB::table('test_attempts as ta')
            ->join('test_questions as tq', 'tq.test_id', '=', 'ta.test_id')
            ->join('questions as q', 'q.id', '=', 'tq.question_id')
            ->leftJoin('attempt_answers as aa', fn ($j) => $j->on('aa.test_attempt_id', '=', 'ta.id')->on('aa.question_id', '=', 'tq.question_id'))
            ->leftJoin('question_options as qo', 'qo.id', '=', 'aa.selected_option_id')
            ->where('ta.user_id', $this->user->id)->whereNotNull('ta.submitted_at')
            ->groupBy('q.chapter_id', 'ta.test_id')
            ->select('q.chapter_id', 'ta.test_id')
            ->selectRaw('count(distinct ta.id) as attempts')
            ->selectRaw('sum(case when qo.is_correct = ? then 1 else 0 end) as correct', [true])
            ->selectRaw('sum(case when aa.selected_option_id is not null and qo.is_correct = ? then 1 else 0 end) as incorrect', [false])
            ->selectRaw('sum(case when aa.selected_option_id is null then 1 else 0 end) as skipped')
            ->get();

        return $rows->groupBy(fn ($r) => $r->chapter_id ?? $testChapter->get($r->test_id))
            ->except([''])
            ->map(fn (Collection $g) => [
                'correct' => (int) $g->sum('correct'),
                'incorrect' => (int) $g->sum('incorrect'),
                'skipped' => (int) $g->sum('skipped'),
                'attempts' => (int) $g->sum('attempts'),
            ]);
    }
}
