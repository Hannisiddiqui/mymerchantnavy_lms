<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Enums\TestKind;
use App\Filament\Resources\AdminResource;
use App\Filament\Resources\AttemptResource;
use App\Filament\Resources\CourseResource;
use App\Filament\Resources\EnrollmentResource;
use App\Filament\Resources\QuestionResource;
use App\Filament\Resources\StudentResource;
use App\Filament\Resources\TestResource;
use App\Models\ActivityLog;
use App\Models\Admin;
use App\Models\Course;
use App\Models\Question;
use App\Models\Setting;
use App\Models\Test;
use App\Models\User;
use App\Services\Auth\DeviceSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SuperAdminTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): Admin
    {
        return Admin::create([
            'name' => 'Super Admin',
            'email' => Admin::SUPER_ADMIN_EMAIL,
            'password' => 'secret-password-123',
        ]);
    }

    private function regularAdmin(string $email = 'admin@example.com'): Admin
    {
        return Admin::create([
            'name' => 'Regular Admin',
            'email' => $email,
            'password' => 'secret-password-123',
            'role' => AdminRole::Admin,
        ]);
    }

    private function editorAdmin(string $email = 'editor@example.com'): Admin
    {
        return Admin::create([
            'name' => 'Course Editor',
            'email' => $email,
            'password' => 'secret-password-123',
            'role' => AdminRole::Editor,
        ]);
    }

    public function test_only_support_email_is_super_admin(): void
    {
        $super = $this->superAdmin();
        $this->assertTrue($super->isSuperAdmin());
        $this->assertSame(AdminRole::SuperAdmin, $super->role);

        $regular = $this->regularAdmin();
        $this->assertFalse($regular->isSuperAdmin());
        $this->assertSame(AdminRole::Admin, $regular->role);

        $editor = $this->editorAdmin();
        $this->assertFalse($editor->isSuperAdmin());
        $this->assertSame(AdminRole::Editor, $editor->role);

        // Attempting to give super_admin role to any other email is blocked
        $this->expectException(ValidationException::class);
        Admin::create([
            'name' => 'Imposter',
            'email' => 'imposter@example.com',
            'password' => 'secret-password-123',
            'role' => AdminRole::SuperAdmin,
        ]);
    }

    public function test_super_admin_cannot_be_deleted(): void
    {
        $super = $this->superAdmin();

        $this->expectException(\Exception::class);
        $super->delete();
    }

    public function test_super_admin_can_access_session_limits(): void
    {
        $super = $this->superAdmin();
        $this->actingAs($super, 'admin')->get('/admin/session-limits')->assertOk();
    }

    public function test_super_admin_can_access_student_deletion_page(): void
    {
        $super = $this->superAdmin();
        $this->actingAs($super, 'admin')->get('/admin/student-account-deletion')->assertOk();
    }

    public function test_super_admin_can_access_website_activity_page(): void
    {
        $super = $this->superAdmin();
        $response = $this->actingAs($super, 'admin')->get('/admin/website-activity');
        $response->assertOk();
        $response->assertSee('Total Activity (24h)');
        $response->assertSee('Admin Actions (24h)');
        $response->assertSee('Student Activity (24h)');
        $response->assertSee('Security & Critical (24h)');
        // Ensure no unconstrained raw SVGs that blow up page height
        $response->assertDontSee('<svg class="h-6 w-6"');
    }

    public function test_admin_can_access_website_activity_page(): void
    {
        $admin = $this->regularAdmin();
        $this->actingAs($admin, 'admin')->get('/admin/website-activity')->assertOk();
    }

    public function test_editor_cannot_access_website_activity_page(): void
    {
        $editor = $this->editorAdmin();
        $this->actingAs($editor, 'admin')->get('/admin/website-activity')->assertForbidden();
    }

    public function test_admin_can_view_data_but_cannot_delete_anything(): void
    {
        $admin = $this->regularAdmin();
        $super = $this->superAdmin();

        $course = Course::create(['title' => 'Sample Course', 'slug' => 'sample-c']);
        $test = Test::create(['course_id' => $course->id, 'title' => 'Test 1', 'kind' => TestKind::Practice, 'duration_minutes' => 30]);
        $question = Question::create(['body' => 'What is 2+2?']);
        $student = User::factory()->create(['email' => 'student.check@example.com']);

        // Admin can view data
        $this->actingAs($admin, 'admin');
        $this->assertTrue(CourseResource::canViewAny());
        $this->assertTrue(TestResource::canViewAny());
        $this->assertTrue(QuestionResource::canViewAny());
        $this->assertTrue(StudentResource::canViewAny());
        $this->assertTrue(EnrollmentResource::canViewAny());
        $this->assertTrue(AttemptResource::canViewAny());

        // Admin CANNOT delete anything
        $this->assertFalse(CourseResource::canDelete($course));
        $this->assertFalse(TestResource::canDelete($test));
        $this->assertFalse(QuestionResource::canDelete($question));
        $this->assertFalse(StudentResource::canDelete($student));
        $this->assertFalse(AdminResource::canDelete($admin));

        // Only Super Admin can delete
        $this->actingAs($super, 'admin');
        $this->assertTrue(CourseResource::canDelete($course));
        $this->assertTrue(TestResource::canDelete($test));
        $this->assertTrue(QuestionResource::canDelete($question));
        $this->assertTrue(StudentResource::canDelete($student));
    }

    public function test_editor_can_only_update_course_content_and_cannot_view_students_or_delete(): void
    {
        $editor = $this->editorAdmin();

        $course = Course::create(['title' => 'Deck Safety', 'slug' => 'deck-safety']);
        $test = Test::create(['course_id' => $course->id, 'title' => 'Safety Quiz', 'kind' => TestKind::Practice, 'duration_minutes' => 20]);
        $question = Question::create(['body' => 'Safety rule 1?']);

        $this->actingAs($editor, 'admin');

        // Editor CAN view and update course content
        $this->assertTrue(CourseResource::canViewAny());
        $this->assertTrue(TestResource::canViewAny());
        $this->assertTrue(QuestionResource::canViewAny());

        // Editor CANNOT view students, enrollments, test results, or admin list
        $this->assertFalse(StudentResource::canViewAny());
        $this->assertFalse(EnrollmentResource::canViewAny());
        $this->assertFalse(AttemptResource::canViewAny());
        $this->assertFalse(AdminResource::canViewAny());

        // Editor CANNOT delete course content
        $this->assertFalse(CourseResource::canDelete($course));
        $this->assertFalse(TestResource::canDelete($test));
        $this->assertFalse(QuestionResource::canDelete($question));

        // Editor cannot access restricted super admin pages
        $this->get('/admin/session-limits')->assertForbidden();
        $this->get('/admin/student-account-deletion')->assertForbidden();
        $this->get('/admin/website-activity')->assertForbidden();
    }

    public function test_super_admin_has_power_to_put_session_limit_and_it_is_enforced(): void
    {
        $student = User::factory()->create(['email' => 'student1@example.com']);
        $service = app(DeviceSessionService::class);

        // When device limit is disabled (0), any number of devices can log in
        Setting::set('device_limit_enabled', '0');
        Setting::set('student_max_devices', 2);
        $service->login($student, 'Phone');
        $service->login($student, 'Laptop');
        $service->login($student, 'Tablet');
        $this->assertSame(3, $student->tokens()->count());

        // When device limit is enabled (1) and max is 2 devices
        Setting::set('device_limit_enabled', '1');
        $service->login($student, 'Desktop');

        // Oldest tokens were evicted, count is capped at 2
        $this->assertSame(2, $student->tokens()->count());
        $this->assertDatabaseHas('system_settings', ['key' => 'student_max_devices', 'value' => '2']);
        $this->assertDatabaseHas('system_settings', ['key' => 'device_limit_enabled', 'value' => '1']);
    }

    public function test_session_policy_api_exposes_device_and_tab_limits(): void
    {
        Setting::set('device_limit_enabled', '1');
        Setting::set('student_max_devices', 2);
        Setting::set('tab_limit_enabled', '1');
        Setting::set('student_max_tabs', 2);

        $response = $this->getJson('/api/v1/session-policy');
        $response->assertOk();
        $response->assertJson([
            'data' => [
                'device_limit_enabled' => true,
                'student_max_devices' => 2,
                'tab_limit_enabled' => true,
                'student_max_tabs' => 2,
            ],
        ]);
    }

    public function test_website_activity_monitors_admin_and_student_actions(): void
    {
        $super = $this->superAdmin();

        // Admin action: create course and test
        $course = Course::create(['title' => 'Navigation Masterclass', 'slug' => 'nav-master']);
        $test = Test::create(['course_id' => $course->id, 'title' => 'ColRegs Quiz', 'kind' => TestKind::Practice, 'duration_minutes' => 15]);

        $this->assertDatabaseHas('activity_logs', [
            'event' => 'course.created',
            'subject_id' => $course->id,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'event' => 'test.created',
            'subject_id' => $test->id,
        ]);

        // Student action: verify OTP / login
        $student = User::factory()->create(['email' => 'cadet@example.com']);
        $this->actingAs($student, 'sanctum');

        ActivityLog::record(
            event: 'student.login',
            description: "Student {$student->email} logged in on device: iPhone 15",
            subject: $student,
            properties: ['device' => 'iPhone 15'],
            student: $student
        );

        $this->assertDatabaseHas('activity_logs', [
            'event' => 'student.login',
            'actor_type' => 'student',
            'actor_email' => 'cadet@example.com',
        ]);
    }
}
