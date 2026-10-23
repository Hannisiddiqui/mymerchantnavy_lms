<?php

namespace Tests\Feature;

use App\Enums\TestKind;
use App\Filament\Resources\StudentResource\Pages\ListStudents;
use App\Models\Admin;
use App\Models\Course;
use App\Models\Test;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AssignCourseAndBackTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Admin
    {
        $admin = Admin::create(['name' => 'A', 'email' => 'a@example.com', 'password' => 'secret-password']);
        $this->actingAs($admin, 'admin');
        Filament::setCurrentPanel('admin');

        return $admin;
    }

    public function test_ticking_courses_assigns_only_the_new_ones(): void
    {
        $this->admin();
        $student = User::factory()->create();
        $a = Course::create(['title' => 'Course A', 'slug' => 'a']);
        $b = Course::create(['title' => 'Course B', 'slug' => 'b']);
        $c = Course::create(['title' => 'Course C', 'slug' => 'c']);
        $student->courses()->attach($a->id, ['assigned_at' => now()]);

        // Only the courses the student does not have yet can be ticked.
        Livewire::test(ListStudents::class)
            ->mountTableAction('assign', $student)
            ->setTableActionData(['courses' => [$a->id]])
            ->callMountedTableAction()
            ->assertHasTableActionErrors()
            ->setTableActionData(['courses' => [$b->id]])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $this->assertEqualsCanonicalizing([$a->id, $b->id], $student->courses()->pluck('courses.id')->all());
        $this->assertFalse($student->courses()->whereKey($c->id)->exists());
    }

    public function test_questions_page_has_a_way_back_to_the_course(): void
    {
        $this->admin();
        $course = Course::create(['title' => 'My Course', 'slug' => 'my-course']);
        $test = Test::create(['course_id' => $course->id, 'title' => 'T', 'kind' => TestKind::Practice, 'duration_minutes' => 10]);

        $html = $this->get("/admin/tests/{$test->id}/questions")->assertOk()->getContent();

        $this->assertStringContainsString('Done — back to course', html_entity_decode($html));
        $this->assertMatchesRegularExpression('#<a\b[^>]*href="[^"]*/courses/'.$course->id.'/curriculum"#', $html);
    }

    public function test_assign_students_from_the_course_page_accepts_picked_students(): void
    {
        $this->admin();
        $course = Course::create(['title' => 'Course A', 'slug' => 'a']);
        $student = User::factory()->create();

        Livewire::test(\App\Filament\Resources\CourseResource\RelationManagers\StudentsRelationManager::class, ['ownerRecord' => $course, 'pageClass' => \App\Filament\Resources\CourseResource\Pages\EditCourse::class])
            ->callTableAction('assign', data: ['students' => [$student->id]])
            ->assertHasNoTableActionErrors();

        $this->assertTrue($course->students()->whereKey($student->id)->exists());
    }
}
