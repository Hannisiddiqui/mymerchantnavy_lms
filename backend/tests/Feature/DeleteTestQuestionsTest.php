<?php

namespace Tests\Feature;

use App\Enums\TestKind;
use App\Models\Course;
use App\Models\Question;
use App\Models\Test;
use App\Services\Tests\QuestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeleteTestQuestionsTest extends TestCase
{
    use RefreshDatabase;

    private function question(string $body): Question
    {
        return app(QuestionService::class)->save(['body' => $body, 'explanation' => 'E', 'correct' => 1, 'option_1' => 'A', 'option_2' => 'B', 'option_3' => 'C', 'option_4' => 'D']);
    }

    private function test(Course $course, string $title): Test
    {
        return Test::create(['course_id' => $course->id, 'title' => $title, 'kind' => TestKind::Practice, 'duration_minutes' => 10]);
    }

    public function test_deleting_a_test_deletes_the_questions_only_it_used(): void
    {
        $course = Course::create(['title' => 'C', 'slug' => 'c']);
        $old = $this->test($course, 'Old');
        $other = $this->test($course, 'Other');
        $own1 = $this->question('own 1');
        $own2 = $this->question('own 2');
        $shared = $this->question('shared');
        $old->questions()->attach([$own1->id => ['position' => 0], $own2->id => ['position' => 1], $shared->id => ['position' => 2]]);
        $other->questions()->attach([$shared->id => ['position' => 0]]);

        $old->delete();

        $this->assertModelMissing($own1);
        $this->assertModelMissing($own2);
        $this->assertDatabaseMissing('question_options', ['question_id' => $own1->id]);
        $this->assertModelExists($shared);
        $this->assertSame(1, $other->fresh()->questions()->count());
        $this->assertModelExists($other);
    }

    public function test_a_question_that_was_not_in_the_deleted_test_is_never_touched(): void
    {
        $course = Course::create(['title' => 'C', 'slug' => 'c']);
        $test = $this->test($course, 'T');
        $inTest = $this->question('in');
        $inBank = $this->question('bank only');
        $test->questions()->attach($inTest->id, ['position' => 0]);

        $test->delete();

        $this->assertModelMissing($inTest);
        $this->assertModelExists($inBank);
    }

    public function test_deleting_a_course_deletes_its_tests_and_their_own_questions(): void
    {
        $course = Course::create(['title' => 'C', 'slug' => 'c']);
        $test = $this->test($course, 'T');
        $q = $this->question('q');
        $test->questions()->attach($q->id, ['position' => 0]);

        $course->delete();

        $this->assertModelMissing($test);
        $this->assertModelMissing($q);
    }

    public function test_new_tests_default_to_two_marks_and_a_quarter_mark_penalty(): void
    {
        $course = Course::create(['title' => 'C', 'slug' => 'c']);
        $test = Test::create(['course_id' => $course->id, 'title' => 'T', 'kind' => TestKind::Practice, 'duration_minutes' => 10])->fresh();

        $this->assertEquals(2.0, $test->marks_correct);
        $this->assertEquals(0.25, $test->marks_wrong);
    }
}
