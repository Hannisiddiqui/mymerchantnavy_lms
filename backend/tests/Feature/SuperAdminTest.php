<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Enums\TestKind;
use App\Filament\Resources\StudentResource;
use App\Models\ActivityLog;
use App\Models\Admin;
use App\Models\Course;
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

    public function test_only_support_email_is_super_admin(): void
    {
        $super = $this->superAdmin();
        $this->assertTrue($super->isSuperAdmin());
        $this->assertSame(AdminRole::SuperAdmin, $super->role);

        $regular = $this->regularAdmin();
        $this->assertFalse($regular->isSuperAdmin());
        $this->assertSame(AdminRole::Admin, $regular->role);

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
        $this->actingAs($super, 'admin')->get('/admin/website-activity')->assertOk();
    }

    public function test_regular_admin_is_forbidden_from_super_admin_pages(): void
    {
        $regular = $this->regularAdmin();

        $this->actingAs($regular, 'admin')->get('/admin/session-limits')->assertForbidden();
        $this->actingAs($regular, 'admin')->get('/admin/student-account-deletion')->assertForbidden();
        $this->actingAs($regular, 'admin')->get('/admin/website-activity')->assertForbidden();
    }

    public function test_super_admin_has_power_to_put_session_limit_and_it_is_enforced(): void
    {
        $student = User::factory()->create(['email' => 'student1@example.com']);
        $service = app(DeviceSessionService::class);

        // Default: 0 = unlimited
        Setting::set('student_max_devices', 0);
        $service->login($student, 'Phone');
        $service->login($student, 'Laptop');
        $service->login($student, 'Tablet');
        $this->assertSame(3, $student->tokens()->count());

        // Super Admin sets session limit to 2 devices
        Setting::set('student_max_devices', 2);
        $service->login($student, 'Desktop');

        // Oldest token was evicted, count is capped at 2
        $this->assertSame(2, $student->tokens()->count());
        $this->assertDatabaseHas('system_settings', ['key' => 'student_max_devices', 'value' => '2']);
    }

    public function test_only_super_admin_can_delete_student_accounts(): void
    {
        $student = User::factory()->create(['email' => 'student.to.delete@example.com']);
        $student->createToken('Mobile')->plainTextToken;

        $super = $this->superAdmin();
        $regular = $this->regularAdmin();

        // Regular admin cannot delete
        $this->actingAs($regular, 'admin');
        $this->assertFalse(StudentResource::canDelete($student));

        // Super Admin can delete
        $this->actingAs($super, 'admin');
        $this->assertTrue(StudentResource::canDelete($student));

        // When deleted, activity log is recorded
        ActivityLog::record(
            event: 'student.deleted',
            description: "Super Admin permanently deleted student {$student->email}",
            subject: null,
            properties: ['email' => $student->email],
            admin: $super
        );
        $student->tokens()->delete();
        $student->delete();

        $this->assertDatabaseMissing('users', ['email' => 'student.to.delete@example.com']);
        $this->assertDatabaseHas('activity_logs', [
            'event' => 'student.deleted',
            'actor_email' => Admin::SUPER_ADMIN_EMAIL,
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
