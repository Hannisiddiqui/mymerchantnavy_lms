<?php

namespace App\Services\Student;

use App\Enums\ContentType;
use App\Enums\TestKind;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Chapter;
use App\Models\ChapterContent;
use App\Models\ContentProgress;
use App\Models\Course;
use App\Models\CurriculumItem;
use App\Models\LiveClass;
use App\Models\Subject;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

/**
 * What ONE student can see: courses assigned to them, their subjects/chapters/content, and published tests.
 * Every lookup is scoped to the student's enrolled courses, so ids from the browser can never reach another course.
 *
 * The arrays returned match the TypeScript types in student-app/src/types/index.ts. Change both together.
 */
class StudentCatalog
{
    /** Banner gradients for course cards (until courses get uploaded thumbnails). */
    private const ACCENTS = [
        'from-navy-900 via-navy-800 to-brand-600',
        'from-brand-700 via-brand-600 to-sky-500',
        'from-navy-800 via-brand-700 to-sky-500',
        'from-slate-800 via-navy-700 to-brand-500',
    ];

    private ?Collection $courseIds = null;

    private ?Collection $completed = null;

    private ?Collection $testStats = null;

    private ?Collection $enrollments = null;

    public function __construct(private User $user, private VideoEmbed $embeds) {}

    // ------------------------------------------------------------------ courses

    /** @return Collection<int,int> */
    public function courseIds(): Collection
    {
        return $this->courseIds ??= $this->user->enrollments()
            ->active()
            ->whereHas('course', fn ($q) => $q->where('is_active', true))
            ->pluck('course_id');
    }

    /** Courses shown in "My Courses": the ones with access, plus expired ones (shown as expired, with a renew message). Cancelled ones are left out. */
    public function courses(): array
    {
        $listed = $this->user->enrollments()->where('status', '!=', 'cancelled')->pluck('course_id');

        return Course::where('is_active', true)->whereIn('id', $listed)
            ->get()
            ->map(fn (Course $c) => $this->courseSummary($c))
            ->values()->all();
    }

    public function course(int $id): array
    {
        $course = $this->accessibleCourse($id);

        $curriculum = $course->curriculum()
            ->with(['item' => fn ($morph) => $morph->morphWith([Subject::class => ['chapters.contents.contentable']])])
            ->get()
            ->map(function (CurriculumItem $entry) {
                $item = $entry->item;
                if ($item instanceof Subject) {
                    return ['kind' => 'subject', 'subject' => $this->subjectSummary($item)];
                }
                if ($item instanceof Test && $item->is_published) {
                    return ['kind' => 'test', 'test' => $this->testSummary($item)];
                }

                return null;
            })
            ->filter()->values()->all();

        return $this->courseSummary($course) + ['curriculum' => $curriculum];
    }

    public function subject(int $id): array
    {
        $subject = Subject::with(['chapters.contents.contentable', 'chapters.test' => fn ($q) => $q->withCount('questions')->with('course')])
            ->whereIn('course_id', $this->courseIds())->findOrFail($id);

        return [
            'id' => $subject->id,
            'courseId' => $subject->course_id,
            'courseTitle' => $subject->course->title,
            'title' => $subject->title,
            'description' => $subject->description ?? '',
            // A mock test placed between chapters comes through as an entry with `test` set and no contents.
            'chapters' => $subject->chapters
                ->reject(fn (Chapter $ch) => $ch->isMockTest() && ! $ch->test?->is_published)
                ->map(fn (Chapter $ch) => [
                    'id' => $ch->id,
                    'title' => $ch->test?->title ?? $ch->title,
                    'test' => $ch->test ? $this->testSummary($ch->test) : null,
                    'contents' => $this->visibleContents($ch)->map(fn (ChapterContent $c) => $this->content($c))->values()->all(),
                ])->values()->all(),
        ];
    }

    /** One content item plus its subject (the player shows the chapter list next to it). */
    public function contentWithSubject(int $id): array
    {
        $content = $this->findContent($id);

        return ['content' => $this->content($content), 'subject' => $this->subject($content->chapter->subject_id)];
    }

    public function findContent(int $id): ChapterContent
    {
        $content = ChapterContent::with('chapter.subject', 'contentable')->findOrFail($id);
        $courseId = $content->chapter->subject->course_id;

        $enrollment = $this->enrollments()->get($courseId);
        if ($enrollment && $enrollment->isExpired()) {
            abort(403, "Your access to this course expired on {$enrollment->expires_at->format('d M Y')}. Please renew your course access to continue.");
        }

        if (! $this->courseIds()->contains($courseId)
            || ($content->contentable instanceof Test && ! $content->contentable->is_published)) {
            throw new ModelNotFoundException;
        }

        return $content;
    }

    public function markComplete(int $contentId): void
    {
        $content = $this->findContent($contentId);

        ContentProgress::firstOrCreate(
            ['user_id' => $this->user->id, 'chapter_content_id' => $content->id],
            ['completed_at' => now()],
        );
    }

    // ------------------------------------------------------------------ tests

    /** Published mock tests from all the student's courses. */
    public function mockTests(): array
    {
        return Test::with('course')->whereIn('course_id', $this->courseIds())
            ->where('kind', TestKind::Mock)->where('is_published', true)->withCount('questions')
            ->orderBy('course_id')->orderBy('id')->get()
            ->map(fn (Test $t) => $this->testSummary($t))->values()->all();
    }

    public function findTest(int $id): Test
    {
        $test = Test::with('course')->withCount('questions')->where('is_published', true)->findOrFail($id);

        $enrollment = $this->enrollments()->get($test->course_id);
        if ($enrollment && $enrollment->isExpired()) {
            abort(403, "Your access to this course test expired on {$enrollment->expires_at->format('d M Y')}. Please renew your course access to continue.");
        }

        abort_unless($this->courseIds()->contains($test->course_id), 404);

        return $test;
    }

    public function testSummary(Test $test): array
    {
        $stats = $this->testStats()->get($test->id);
        $questions = $test->questions_count ?? $test->questions()->count();

        return [
            'id' => $test->id,
            'title' => $test->title,
            'kind' => $test->kind->value,
            'courseId' => $test->course_id,
            'courseTitle' => $test->course->title,
            'questionsCount' => $questions,
            'durationMinutes' => $test->duration_minutes,
            'marksCorrect' => $test->marks_correct,
            'marksWrong' => $test->marks_wrong,
            'totalMarks' => $questions * $test->marks_correct,
            'attemptsCount' => $stats['count'] ?? 0,
            'bestScore' => $stats['best'] ?? null,
            'lastAttemptId' => $stats['last'] ?? null,
        ];
    }

    // ------------------------------------------------------------------ internals

    private function accessibleCourse(int $id): Course
    {
        $enrollment = $this->enrollments()->get($id);
        if ($enrollment && $enrollment->isExpired()) {
            abort(403, "Your access to this course expired on {$enrollment->expires_at->format('d M Y')}. Please renew your course access to continue.");
        }

        abort_unless($this->courseIds()->contains($id), 404);

        return Course::findOrFail($id);
    }

    private function courseSummary(Course $course): array
    {
        $subjectIds = $course->subjects()->pluck('id');
        $chapterIds = Chapter::realChapters()->whereIn('subject_id', $subjectIds)->pluck('id');
        $contentIds = ChapterContent::whereIn('chapter_id', $chapterIds)->pluck('id');
        $done = $contentIds->intersect($this->completed())->count();

        // Practice tests are counted per chapter: a chapter whose test is split into parts (Part 1 of 3 …) counts once,
        // so the number lines up with the chapters. Mock tests are counted on their own.
        $practice = ChapterContent::with('contentable')->whereIn('chapter_id', $chapterIds)->where('type', ContentType::PracticeTest)->get()
            ->filter(fn (ChapterContent $c) => $c->contentable instanceof Test && $c->contentable->is_published)
            ->pluck('chapter_id')->unique()->count();
        $mock = $course->tests()->where('kind', TestKind::Mock)->where('is_published', true)->count();

        $enrollment = $this->enrollments()->get($course->id);

        return [
            'id' => $course->id,
            'title' => $course->title,
            'description' => $course->description ?? '',
            'accent' => self::ACCENTS[$course->id % count(self::ACCENTS)],
            'subjectsCount' => $subjectIds->count(),
            'chaptersCount' => $chapterIds->count(),
            'practiceTestsCount' => $practice,
            'mockTestsCount' => $mock,
            'testsCount' => $practice + $mock, // older versions of the student site read this one
            'progressPercent' => $contentIds->isEmpty() ? 0 : (int) round($done / $contentIds->count() * 100),
            'assignedAt' => $enrollment?->assigned_at?->toIso8601String(),
            'purchaseDate' => $enrollment?->purchase_date?->toIso8601String(),
            'expiresAt' => $enrollment?->expires_at?->toIso8601String(),
            'purchaseType' => $enrollment?->purchase_type ?? 'assigned',
            'remainingDuration' => $enrollment?->remainingDuration(),
            'isExpired' => $enrollment?->isExpired() ?? false,
        ];
    }

    private function subjectSummary(Subject $subject): array
    {
        $contents = $subject->chapters->flatMap(fn (Chapter $ch) => $this->visibleContents($ch));
        $done = $contents->pluck('id')->intersect($this->completed())->count();

        return [
            'id' => $subject->id,
            'title' => $subject->title,
            'description' => $subject->description ?? '',
            'chaptersCount' => $subject->chapters->reject->isMockTest()->count(),
            'contentsCount' => $contents->count(),
            'progressPercent' => $contents->isEmpty() ? 0 : (int) round($done / $contents->count() * 100),
        ];
    }

    /** Content the student may see: unpublished practice tests are hidden. */
    private function visibleContents(Chapter $chapter): Collection
    {
        return $chapter->contents->filter(fn (ChapterContent $c) => $c->contentable
            && ! ($c->contentable instanceof Test && ! $c->contentable->is_published));
    }

    private function content(ChapterContent $c): array
    {
        $m = $c->contentable;
        $base = [
            'id' => $c->id,
            'chapterId' => $c->chapter_id,
            'type' => $c->type->value,
            'title' => $c->title,
            'completed' => $this->completed()->contains($c->id),
        ];

        return $base + match ($c->type) {
            ContentType::Video => [
                'durationSeconds' => $m->duration_seconds ?? 0,
                'provider' => $m->provider->value,
                'embedUrl' => $this->embeds->url($m),
            ],
            ContentType::LiveClass => $this->liveClass($m),
            ContentType::Pdf => ['pageCount' => $m->page_count ?? 0],
            ContentType::Note => ['readMinutes' => max(1, (int) ceil(str_word_count(strip_tags($m->body)) / 200)), 'body' => $m->body],
            ContentType::Assignment => $this->assignment($m),
            ContentType::PracticeTest => ['test' => $this->testSummary($m->loadMissing('course')->loadCount('questions'))],
        };
    }

    private function liveClass(LiveClass $live): array
    {
        $recording = $live->recording;

        return [
            'startsAt' => $live->starts_at->toIso8601String(),
            'durationMinutes' => $live->duration_minutes,
            'status' => $live->status(),
            'recording' => $recording ? ['durationSeconds' => $recording->duration_seconds ?? 0, 'embedUrl' => $this->embeds->url($recording)] : null,
        ];
    }

    private function assignment(Assignment $assignment): array
    {
        $submission = AssignmentSubmission::where('assignment_id', $assignment->id)->where('user_id', $this->user->id)->first();

        return [
            'dueAt' => $assignment->due_at?->toIso8601String(),
            'instructions' => $assignment->instructions,
            'submittedAt' => $submission?->submitted_at->toIso8601String(),
        ];
    }

    private function enrollments(): Collection
    {
        return $this->enrollments ??= $this->user->enrollments()->get()->keyBy('course_id');
    }

    /**
     * Ids of content this student has completed: marked or finished by reading/watching, plus the ones that finish by
     * DOING something: a practice test counts once it has been submitted, an assignment once a file was handed in.
     * (Derived from the student's own attempts/submissions, so it is right even for work done before this rule existed.)
     */
    private function completed(): Collection
    {
        return $this->completed ??= ContentProgress::where('user_id', $this->user->id)->pluck('chapter_content_id')
            ->merge($this->doneByAction(ContentType::PracticeTest, (new Test)->getMorphClass(), $this->testStats()->keys()))
            ->merge($this->doneByAction(
                ContentType::Assignment,
                (new Assignment)->getMorphClass(),
                AssignmentSubmission::where('user_id', $this->user->id)->pluck('assignment_id'),
            ))
            ->unique()->values();
    }

    /** Content of this type whose test/assignment the student has already taken or handed in. */
    private function doneByAction(ContentType $type, string $morph, Collection $materialIds): Collection
    {
        if ($materialIds->isEmpty()) {
            return collect();
        }

        return ChapterContent::where('type', $type)->where('contentable_type', $morph)
            ->whereIn('contentable_id', $materialIds)->pluck('id');
    }

    /** Per test: how many submitted attempts, best score, last attempt uuid. */
    private function testStats(): Collection
    {
        return $this->testStats ??= TestAttempt::where('user_id', $this->user->id)->whereNotNull('submitted_at')
            ->orderBy('id')->get(['uuid', 'test_id', 'score'])
            ->groupBy('test_id')
            ->map(fn (Collection $rows) => ['count' => $rows->count(), 'best' => $rows->max('score'), 'last' => $rows->last()->uuid]);
    }

    /** Forget cached numbers (after submitting a test or completing content). */
    public function refresh(): void
    {
        $this->completed = $this->testStats = null;
    }
}
