<?php

namespace Tests\Feature;

use App\Enums\ContentType;
use App\Enums\ReportStatus;
use App\Enums\TestKind;
use App\Filament\Resources\QuestionReportResource\Pages\ListQuestionReports;
use App\Models\Admin;
use App\Models\Chapter;
use App\Models\Course;
use App\Models\Question;
use App\Models\QuestionReport;
use App\Models\Subject;
use App\Models\Test;
use App\Models\User;
use App\Services\Courses\CourseOutline;
use App\Services\Courses\EnrollmentService;
use App\Services\Tests\QuestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Reporting a question from the result page, closing it in the admin, and the performance dashboard numbers. */
class QuestionReportsAndPerformanceTest extends TestCase
{
    use RefreshDatabase;

    private Course $course;

    private Subject $subject;

    private Chapter $chapter;

    private Test $practice;

    /** @var array<int,Question> */
    private array $questions = [];

    protected function setUp(): void
    {
        parent::setUp();

        $outline = app(CourseOutline::class);
        $this->course = Course::create(['title' => 'GP Rating', 'slug' => 'gp']);
        $this->subject = $outline->addSubject($this->course, 'Maths');
        $this->chapter = $outline->addChapter($this->subject, 'Percentage');
        $outline->addChapter($this->subject, 'Average'); // never attempted

        // Practice test of 4 questions inside the chapter; option 2 is right everywhere. +2 / -0.25.
        $this->practice = Test::create(['course_id' => $this->course->id, 'title' => 'Percentage Practice Test', 'kind' => TestKind::Practice,
            'duration_minutes' => 90, 'marks_correct' => 2, 'marks_wrong' => 0.25, 'is_published' => true]);
        foreach (range(1, 4) as $n) {
            $q = app(QuestionService::class)->save([
                'body' => "Q{$n}", 'explanation' => "Because {$n}", 'correct' => 2,
                'option_1' => 'A', 'option_2' => 'B', 'option_3' => 'C', 'option_4' => 'D',
                'subject_id' => $this->subject->id, 'chapter_id' => $this->chapter->id,
            ]);
            $this->practice->questions()->attach($q->id, ['position' => $n]);
            $this->questions[] = $q;
        }
        $outline->saveContent($this->chapter, ContentType::PracticeTest, ['title' => 'Practice', 'test_id' => $this->practice->id]);
    }

    private function student(): User
    {
        $user = User::factory()->create();
        app(EnrollmentService::class)->assign($user, $this->course);

        return $user;
    }

    /** @param  array<int,int|null>  $picks  option position per question (null = skipped) */
    private function attempt(User $user, array $picks): string
    {
        $session = $this->actingAs($user, 'sanctum')->postJson("/api/v1/tests/{$this->practice->id}/attempts")->assertOk()->json('data');
        foreach ($picks as $i => $position) {
            $this->putJson("/api/v1/attempts/{$session['attemptId']}/answers/{$this->questions[$i]->id}", [
                'selectedOptionId' => $position ? $this->questions[$i]->options()->where('position', $position)->value('id') : null,
                'markedForReview' => false, 'visited' => true,
            ])->assertOk();
        }
        $this->postJson("/api/v1/attempts/{$session['attemptId']}/submit")->assertOk();

        return $session['attemptId'];
    }

    // ------------------------------------------------------------------ reports

    public function test_student_reports_a_question_and_sees_it_on_the_result(): void
    {
        $student = $this->student();
        $attempt = $this->attempt($student, [2, 1, null, 2]);
        $q = $this->questions[1];

        $this->postJson("/api/v1/attempts/{$attempt}/questions/{$q->id}/report", ['reason' => 'wrong_answer', 'details' => 'Answer should be A'])
            ->assertCreated()->assertJsonPath('data.status', 'open');
        // Reporting again while it is open updates the same report
        $this->postJson("/api/v1/attempts/{$attempt}/questions/{$q->id}/report", ['reason' => 'option_error', 'details' => 'Option A has a typo'])
            ->assertCreated();

        $this->assertSame(1, QuestionReport::count());
        $report = QuestionReport::first();
        $this->assertSame('option_error', $report->reason->value);
        $this->assertSame($this->practice->id, $report->test_id);

        $solutions = collect($this->getJson("/api/v1/attempts/{$attempt}/result")->assertOk()->json('data.solutions'))->keyBy('id');
        $this->assertSame('option_error', $solutions[$q->id]['report']['reason']);
        $this->assertNull($solutions[$this->questions[0]->id]['report']);
    }

    public function test_report_rules(): void
    {
        $student = $this->student();
        $attempt = $this->attempt($student, [2, 2, 2, 2]);
        $other = app(QuestionService::class)->save(['body' => 'Elsewhere', 'correct' => 1, 'option_1' => 'A', 'option_2' => 'B', 'option_3' => 'C', 'option_4' => 'D']);

        // "Something else" needs details; unknown reasons are refused
        $this->postJson("/api/v1/attempts/{$attempt}/questions/{$this->questions[0]->id}/report", ['reason' => 'other'])->assertUnprocessable();
        $this->postJson("/api/v1/attempts/{$attempt}/questions/{$this->questions[0]->id}/report", ['reason' => 'nope'])->assertUnprocessable();
        // A question that is not in this test
        $this->postJson("/api/v1/attempts/{$attempt}/questions/{$other->id}/report", ['reason' => 'typo'])->assertUnprocessable();
        $this->postJson("/api/v1/attempts/{$attempt}/questions/{$other->id}/report", ['reason' => 'formatting'])->assertNotFound();
        // Another student's attempt
        $this->actingAs($this->student(), 'sanctum')
            ->postJson("/api/v1/attempts/{$attempt}/questions/{$this->questions[0]->id}/report", ['reason' => 'formatting'])->assertNotFound();
        // An attempt still in progress
        $running = $this->actingAs($student, 'sanctum')->postJson("/api/v1/tests/{$this->practice->id}/attempts")->json('data.attemptId');
        $this->postJson("/api/v1/attempts/{$running}/questions/{$this->questions[0]->id}/report", ['reason' => 'formatting'])->assertNotFound();
    }

    public function test_admin_closes_a_report_and_the_student_is_told(): void
    {
        $student = $this->student();
        $attempt = $this->attempt($student, [2, 1, 2, 2]);
        $this->postJson("/api/v1/attempts/{$attempt}/questions/{$this->questions[1]->id}/report", ['reason' => 'wrong_answer'])->assertCreated();
        $report = QuestionReport::first();

        $this->actingAs(Admin::create(['name' => 'A', 'email' => 'a@example.com', 'password' => 'secret-password']), 'admin');
        $this->get('/admin/question-reports')->assertOk()->assertSee('Answer key is wrong');

        Livewire::test(ListQuestionReports::class)
            ->assertCanSeeTableRecords([$report])
            ->callTableAction('reject', $report, ['admin_note' => 'The key is right: see step 2.']);

        $report->refresh();
        $this->assertSame(ReportStatus::Rejected, $report->status);
        $this->assertNotNull($report->resolved_at);

        $note = collect($this->actingAs($student, 'sanctum')->getJson('/api/v1/notifications')->json('data'))->firstWhere('type', 'report');
        $this->assertStringContainsString('The key is right', $note['body']);
        $this->assertSame("/results/{$attempt}", $note['href']);
    }

    public function test_admin_fixes_the_question_from_the_report(): void
    {
        $student = $this->student();
        $attempt = $this->attempt($student, [2, 1, 2, 2]);
        $this->postJson("/api/v1/attempts/{$attempt}/questions/{$this->questions[1]->id}/report", ['reason' => 'question_error', 'details' => 'Typo'])->assertCreated();
        $report = QuestionReport::first();

        $this->actingAs(Admin::create(['name' => 'A', 'email' => 'a@example.com', 'password' => 'secret-password']), 'admin');
        Livewire::test(ListQuestionReports::class)
            ->callTableAction('fix', $report, ['body' => '<p>Q2 fixed</p>', 'close_report' => true, 'admin_note' => 'Fixed, thanks'])
            ->assertHasNoTableActionErrors();

        $this->assertSame('<p>Q2 fixed</p>', $this->questions[1]->fresh()->body);
        $this->assertSame(ReportStatus::Resolved, $report->fresh()->status);
    }

    // ------------------------------------------------------------------ performance

    public function test_performance_by_subject_and_chapter(): void
    {
        $student = $this->student();
        $this->attempt($student, [2, 1, null, 2]); // 2 right, 1 wrong, 1 skipped → 3.75 / 8
        $this->attempt($student, [2, 2, 2, 1]);    // 3 right, 1 wrong → 5.75 / 8

        $data = $this->getJson('/api/v1/performance')->assertOk()->json('data');

        $this->assertSame(1, $data['overview']['testsTaken']);
        $this->assertSame(2, $data['overview']['attempts']);
        $this->assertSame(5, $data['overview']['correct']);
        $this->assertSame(2, $data['overview']['incorrect']);
        $this->assertSame(71, $data['overview']['accuracy']);
        $this->assertSame(1, $data['overview']['chaptersStarted']);
        $this->assertSame(2, $data['overview']['chaptersTotal']);
        $this->assertCount(2, $data['trend']);
        $this->assertEquals(46.9, $data['trend'][0]['scorePercent']);

        $maths = $data['subjects'][0];
        $this->assertSame('Maths', $maths['title']);
        $this->assertSame(1, $maths['chaptersStarted']);

        [$percentage, $average] = $maths['chapters'];
        $this->assertSame('Percentage', $percentage['title']);
        $this->assertSame(['correct' => 5, 'incorrect' => 2, 'skipped' => 1, 'accuracy' => 71, 'attempts' => 2],
            array_intersect_key($percentage, array_flip(['correct', 'incorrect', 'skipped', 'accuracy', 'attempts'])));
        $this->assertEquals(71.9, $percentage['bestScorePercent']);
        $this->assertEquals(71.9, $percentage['lastScorePercent']);
        $this->assertSame($this->practice->id, $percentage['testId']);

        $this->assertSame(0, $average['attempts']);
        $this->assertNull($average['accuracy']);
    }

    public function test_performance_with_no_attempts(): void
    {
        $this->actingAs($this->student(), 'sanctum');
        $data = $this->getJson('/api/v1/performance')->assertOk()->json('data');

        $this->assertSame(0, $data['overview']['attempts']);
        $this->assertNull($data['overview']['accuracy']);
        $this->assertSame([], $data['trend']);
        $this->assertCount(2, $data['subjects'][0]['chapters']);
    }
}
