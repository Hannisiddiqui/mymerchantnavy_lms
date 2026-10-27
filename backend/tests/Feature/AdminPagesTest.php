<?php

namespace Tests\Feature;

use App\Enums\ImportMethod;
use App\Enums\TestKind;
use App\Models\Admin;
use App\Models\Course;
use App\Models\CurriculumItem;
use App\Models\QuestionImport;
use App\Models\Test;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Smoke test: every admin page opens for a logged-in admin, and nothing opens for a stranger. */
class AdminPagesTest extends TestCase
{
    public function test_front_door_redirects_instead_of_showing_a_placeholder(): void
    {
        $this->get('/')->assertRedirect('/admin');

        config(['lms.admin_domain' => 'admin.example.test', 'lms.student_app_url' => 'https://app.example.test']);
        $this->get('http://admin.example.test/')->assertRedirect('/login');
        $this->get('http://api.example.test/')->assertRedirect('https://app.example.test');
    }

    use RefreshDatabase;

    private function pages(): array
    {
        $course = Course::create(['title' => 'C', 'slug' => 'c']);
        $test = Test::create(['course_id' => $course->id, 'title' => 'T', 'kind' => TestKind::Practice, 'duration_minutes' => 10]);
        $student = User::factory()->create();
        $import = QuestionImport::create(['method' => ImportMethod::Pdf, 'original_name' => 'x.pdf', 'file_path' => 'x.pdf', 'status' => 'ready']);

        return [
            '/admin', '/admin/courses', '/admin/courses/create', "/admin/courses/{$course->id}/edit", "/admin/courses/{$course->id}/curriculum",
            '/admin/tests', '/admin/tests/create', "/admin/tests/{$test->id}/edit", "/admin/tests/{$test->id}/questions",
            '/admin/questions', '/admin/questions/create',
            '/admin/question-imports', "/admin/question-imports/{$import->id}/review",
            '/admin/students', '/admin/students/create', "/admin/students/{$student->id}/edit",
            '/admin/attempts', '/admin/question-reports', '/admin/enrollments', '/admin/question-analysis', '/admin/admins', '/admin/admins/create',
        ];
    }

    public function test_every_admin_page_opens(): void
    {
        $pages = $this->pages();
        $this->actingAs(Admin::create(['name' => 'A', 'email' => 'a@example.com', 'password' => 'secret-password']), 'admin');

        foreach ($pages as $url) {
            $this->get($url)->assertOk("{$url} should open");
        }
    }

    public function test_course_builder_questions_button_is_a_real_link(): void
    {
        $course = Course::create(['title' => 'C', 'slug' => 'c']);
        $mock = Test::create(['course_id' => $course->id, 'title' => 'Mock', 'kind' => TestKind::Mock, 'duration_minutes' => 10]);
        CurriculumItem::create(['course_id' => $course->id, 'item_type' => $mock->getMorphClass(), 'item_id' => $mock->id, 'position' => 0]);
        $this->actingAs(Admin::create(['name' => 'A', 'email' => 'a@example.com', 'password' => 'secret-password']), 'admin');

        $html = $this->get("/admin/courses/{$course->id}/curriculum")->assertOk()->getContent();

        // A <button> with an href attribute does nothing when clicked; it has to be an <a>.
        $this->assertMatchesRegularExpression('#<a\b[^>]*href="[^"]*/tests/'.$mock->id.'/questions"#', $html);
        $this->assertMatchesRegularExpression('#<a\b[^>]*href="[^"]*/tests/'.$mock->id.'/edit"#', $html);
    }

    public function test_strangers_are_sent_to_the_login_page(): void
    {
        foreach ($this->pages() as $url) {
            $this->get($url)->assertRedirect('/admin/login');
        }
    }

    public function test_a_student_login_cannot_open_the_admin_panel(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum');

        $this->get('/admin/students')->assertRedirect('/admin/login');
    }
}
