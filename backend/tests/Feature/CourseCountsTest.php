<?php

namespace Tests\Feature;

use App\Enums\ContentType;
use App\Enums\TestKind;
use App\Models\Course;
use App\Models\Test;
use App\Models\User;
use App\Services\Courses\CourseOutline;
use App\Services\Courses\EnrollmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The numbers on a course: subjects, chapters, practice tests (per chapter) and mock tests are separate. */
class CourseCountsTest extends TestCase
{
    use RefreshDatabase;

    private function test(Course $course, string $title, TestKind $kind, bool $published = true): Test
    {
        return Test::create(['course_id' => $course->id, 'title' => $title, 'kind' => $kind, 'duration_minutes' => 90, 'is_published' => $published]);
    }

    public function test_counts_are_separate_and_practice_tests_follow_the_chapters(): void
    {
        $outline = app(CourseOutline::class);
        $course = Course::create(['title' => 'GP Rating', 'slug' => 'gp']);
        $geo = $outline->addSubject($course, 'General Knowledge');
        $world = $outline->addChapter($geo, 'World Geography');
        $sports = $outline->addChapter($geo, 'Sports');
        $outline->addChapter($geo, 'Chapter without a test');

        // World Geography's test is split into three parts: still ONE chapter with a practice test
        foreach ([1, 2, 3] as $part) {
            $outline->saveContent($world, ContentType::PracticeTest, ['title' => "Part {$part}", 'test_id' => $this->test($course, "World {$part}", TestKind::Practice)->id]);
        }
        $outline->saveContent($sports, ContentType::PracticeTest, ['title' => 'Sports', 'test_id' => $this->test($course, 'Sports', TestKind::Practice)->id]);
        // an unpublished practice test does not count
        $outline->saveContent($sports, ContentType::PracticeTest, ['title' => 'Hidden', 'test_id' => $this->test($course, 'Hidden', TestKind::Practice, false)->id]);

        // two published mock tests below the subjects, one unpublished
        $outline->addMockTest($course, $this->test($course, 'Mock 1', TestKind::Mock));
        $outline->addMockTest($course, $this->test($course, 'Mock 2', TestKind::Mock));
        $outline->addMockTest($course, $this->test($course, 'Mock draft', TestKind::Mock, false));

        $student = User::factory()->create();
        app(EnrollmentService::class)->assign($student, $course);
        $data = $this->actingAs($student, 'sanctum')->getJson("/api/v1/courses/{$course->id}")->assertOk()->json('data');

        $this->assertSame(1, $data['subjectsCount']);
        $this->assertSame(3, $data['chaptersCount']);
        $this->assertSame(2, $data['practiceTestsCount'], 'World Geography (3 parts) and Sports count once each');
        $this->assertSame(2, $data['mockTestsCount']);
        $this->assertSame(4, $data['testsCount']);

        $card = collect($this->getJson('/api/v1/courses')->assertOk()->json('data'))->firstWhere('id', $course->id);
        $this->assertSame([3, 2, 2], [$card['chaptersCount'], $card['practiceTestsCount'], $card['mockTestsCount']]);
    }
}
