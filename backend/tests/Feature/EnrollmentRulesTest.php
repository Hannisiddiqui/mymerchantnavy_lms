<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Admin;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Notifications\CourseAssigned;
use App\Services\Courses\EnrollmentService;
use App\Services\WordPress\WordPressLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Renewal policy, paid/free labelling, expired courses in "My Courses", and the audit log never storing secrets. */
class EnrollmentRulesTest extends TestCase
{
    use RefreshDatabase;

    private function course(string $type = '6_months'): Course
    {
        return Course::create(['title' => 'GP Rating', 'slug' => 'gp', 'is_active' => true, 'validity_type' => $type, 'wordpress_product_id' => 'gp-pass']);
    }

    public function test_a_new_order_while_active_extends_from_the_current_expiry(): void
    {
        Notification::fake();
        $course = $this->course();
        $student = User::factory()->create();
        $svc = app(EnrollmentService::class);

        $svc->assign($student, $course, null, now()->subMonths(2), 'paid', 'ORD-1');
        $first = Enrollment::first()->expires_at;                     // 4 months from now

        $this->assertTrue($svc->assign($student, $course, null, now(), 'paid', 'ORD-2'));
        $this->assertEquals($first->copy()->addMonthsNoOverflow(6)->toDateString(), Enrollment::first()->expires_at->toDateString());
        $this->assertSame(1, Enrollment::count());
    }

    public function test_a_new_order_after_expiry_starts_again_from_the_purchase_date(): void
    {
        Notification::fake();
        $course = $this->course();
        $student = User::factory()->create();
        $svc = app(EnrollmentService::class);

        $svc->assign($student, $course, null, now()->subMonths(8), 'paid', 'ORD-1'); // expired two months ago
        $this->assertTrue(Enrollment::first()->isExpired());

        $svc->assign($student, $course, null, now(), 'paid', 'ORD-2');
        $this->assertEquals(now()->addMonthsNoOverflow(6)->toDateString(), Enrollment::first()->expires_at->toDateString());
        $this->assertTrue(Enrollment::first()->isActive());
    }

    public function test_the_same_order_twice_changes_nothing(): void
    {
        Notification::fake();
        $course = $this->course();
        $student = User::factory()->create();
        $svc = app(EnrollmentService::class);

        $svc->assign($student, $course, null, now(), 'paid', 'ORD-1');
        $expiry = Enrollment::first()->expires_at;

        $this->assertFalse($svc->assign($student, $course, null, now()->addDays(3), 'paid', 'ORD-1'));
        $this->assertEquals($expiry->toIso8601String(), Enrollment::first()->expires_at->toIso8601String());
    }

    public function test_an_admin_can_reassign_an_expired_course_but_not_an_active_one(): void
    {
        Notification::fake();
        $course = $this->course();
        $student = User::factory()->create();
        $admin = Admin::factory()->create();
        $svc = app(EnrollmentService::class);

        $svc->assign($student, $course, null, now()->subMonths(8), 'paid', 'ORD-1');
        $this->assertTrue($svc->assign($student, $course, $admin));              // expired: starts again from today
        $this->assertEquals(now()->addMonthsNoOverflow(6)->toDateString(), Enrollment::first()->expires_at->toDateString());

        $this->assertFalse($svc->assign($student, $course, $admin));             // still active: nothing happens
    }

    public function test_the_student_is_emailed_the_expiry_date(): void
    {
        Notification::fake();
        $student = User::factory()->create();

        app(EnrollmentService::class)->assign($student, $this->course('1_year'), null, now(), 'paid', 'ORD-9');

        Notification::assertSentTo($student, CourseAssigned::class, function (CourseAssigned $n) use ($student) {
            return str_contains(implode(' ', $n->toMail($student)->introLines), now()->addYearNoOverflow()->format('d F Y'));
        });
    }

    public function test_wordpress_free_signups_are_labelled_free_and_paid_ones_paid(): void
    {
        Notification::fake();
        $this->course();
        $link = app(WordPressLink::class);
        $free = User::factory()->create();
        $paid = User::factory()->create();

        $link->enroll($free, 'gp-pass', 'mmn-1', null, 0.0);
        $this->assertSame('free', Enrollment::where('user_id', $free->id)->value('purchase_type'));

        $link->enroll($paid, 'gp-pass', 'mmn-2', 'pay_1', 4999.0);
        $this->assertSame('paid', Enrollment::where('user_id', $paid->id)->value('purchase_type'));
        $this->assertEquals(4999, (float) Enrollment::where('user_id', $paid->id)->value('amount'));
    }

    public function test_an_expired_course_stays_in_my_courses_marked_expired_but_cannot_be_opened(): void
    {
        Notification::fake();
        $course = $this->course();
        $student = User::factory()->create();
        app(EnrollmentService::class)->assign($student, $course, null, now()->subMonths(8), 'paid', 'ORD-1');

        $list = $this->actingAs($student, 'sanctum')->getJson('/api/v1/courses')->assertOk()->json('data');
        $this->assertCount(1, $list);
        $this->assertTrue($list[0]['isExpired']);
        $this->assertNotNull($list[0]['expiresAt']);

        $this->getJson("/api/v1/courses/{$course->id}")->assertForbidden();
    }

    public function test_the_audit_log_keeps_old_values_and_never_a_password(): void
    {
        $admin = Admin::factory()->create(['name' => 'Old Name']);
        $admin->update(['name' => 'New Name', 'password' => 'a-brand-new-password']);

        $log = ActivityLog::where('event', 'admin.updated')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame('[changed]', $log->properties['dirty']['password']);
        $this->assertSame('Old Name', $log->properties['old']['name']);
        $this->assertSame('New Name', $log->properties['dirty']['name']);
        $this->assertStringNotContainsString('$2y$', json_encode($log->properties));
    }
}
