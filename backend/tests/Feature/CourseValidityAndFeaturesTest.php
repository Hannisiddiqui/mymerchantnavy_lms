<?php

namespace Tests\Feature;

use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Models\Admin;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Question;
use App\Models\QuestionReport;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\User;
use App\Notifications\BulkQuestionReportsClosed;
use App\Notifications\QuestionReportClosed;
use App\Services\Courses\EnrollmentService;
use App\Services\Student\StudentCatalog;
use App\Services\Tests\QuestionReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CourseValidityAndFeaturesTest extends TestCase
{
    use RefreshDatabase;

    public function test_six_month_calendar_arithmetic(): void
    {
        $course = Course::create([
            'title' => 'DNS Course',
            'slug' => 'dns-course',
            'validity_type' => '6_months',
            'is_active' => true,
        ]);

        // 1 January 2027 + 6 months -> 1 July 2027
        $p1 = Carbon::create(2027, 1, 1, 10, 0, 0);
        $exp1 = $course->calculateExpiry($p1);
        $this->assertEquals('2027-07-01 10:00:00', $exp1->format('Y-m-d H:i:s'));

        // 15 January 2027 + 6 months -> 15 July 2027
        $p2 = Carbon::create(2027, 1, 15, 12, 30, 0);
        $exp2 = $course->calculateExpiry($p2);
        $this->assertEquals('2027-07-15 12:30:00', $exp2->format('Y-m-d H:i:s'));

        // 31 March 2027 + 6 months -> 30 September 2027 (no overflow into October)
        $p3 = Carbon::create(2027, 3, 31, 0, 0, 0);
        $exp3 = $course->calculateExpiry($p3);
        $this->assertEquals('2027-09-30 00:00:00', $exp3->format('Y-m-d H:i:s'));
    }

    public function test_one_year_calendar_arithmetic(): void
    {
        $course = Course::create([
            'title' => 'IMU-CET Annual',
            'slug' => 'imu-cet-annual',
            'validity_type' => '1_year',
            'is_active' => true,
        ]);

        $purchase = Carbon::create(2027, 1, 1, 14, 0, 0);
        $expiry = $course->calculateExpiry($purchase);
        $this->assertEquals('2028-01-01 14:00:00', $expiry->format('Y-m-d H:i:s'));
    }

    public function test_custom_validity_calendar_arithmetic(): void
    {
        $course = Course::create([
            'title' => 'Fast Track 3 Months',
            'slug' => 'fast-track-3m',
            'validity_type' => 'custom',
            'validity_duration_months' => 3,
            'is_active' => true,
        ]);

        $purchase = Carbon::create(2027, 2, 1, 10, 0, 0);
        $expiry = $course->calculateExpiry($purchase);
        $this->assertEquals('2027-05-01 10:00:00', $expiry->format('Y-m-d H:i:s'));
    }

    public function test_automatic_course_locking_on_expiry(): void
    {
        $student = User::factory()->create(['email' => 'student.expiry@example.com']);
        $course = Course::create([
            'title' => 'Locked Course',
            'slug' => 'locked-course',
            'validity_type' => '6_months',
            'is_active' => true,
        ]);

        // Student purchased 7 months ago -> expired 1 month ago
        $purchaseDate = now()->subMonths(7);
        $service = app(EnrollmentService::class);
        $service->assign(
            student: $student,
            course: $course,
            by: null,
            purchaseDate: $purchaseDate,
            purchaseType: 'paid',
            orderId: 'ORD-1001',
            amount: 4999.00
        );

        $enrollment = Enrollment::where('user_id', $student->id)->where('course_id', $course->id)->first();
        $this->assertTrue($enrollment->isExpired());
        $this->assertFalse($enrollment->isActive());

        // Student catalog should exclude the expired course
        $catalog = app(StudentCatalog::class, ['user' => $student]);
        $this->assertFalse($catalog->courseIds()->contains($course->id));
    }

    public function test_unrelated_course_remains_active_when_another_expires(): void
    {
        $student = User::factory()->create(['email' => 'multi.courses@example.com']);

        $course1 = Course::create(['title' => 'Expired Course', 'slug' => 'c1', 'validity_type' => '6_months', 'is_active' => true]);
        $course2 = Course::create(['title' => 'Active Course', 'slug' => 'c2', 'validity_type' => '1_year', 'is_active' => true]);

        $service = app(EnrollmentService::class);

        // Course 1 expired
        $service->assign($student, $course1, null, now()->subMonths(8), 'paid', 'ORD-C1');

        // Course 2 active (purchased 1 month ago with 1 year validity)
        $service->assign($student, $course2, null, now()->subMonths(1), 'paid', 'ORD-C2');

        $catalog = app(StudentCatalog::class, ['user' => $student]);

        $this->assertFalse($catalog->courseIds()->contains($course1->id));
        $this->assertTrue($catalog->courseIds()->contains($course2->id));
    }

    public function test_webhook_idempotency_does_not_rewrite_expiry(): void
    {
        $student = User::factory()->create(['email' => 'webhook.idempotent@example.com']);
        $course = Course::create(['title' => 'Idempotent Course', 'slug' => 'idem-course', 'validity_type' => '6_months', 'is_active' => true]);

        $service = app(EnrollmentService::class);
        $purchaseTime = Carbon::create(2027, 1, 1, 10, 0, 0);

        // First purchase webhook call
        $firstResult = $service->assign($student, $course, null, $purchaseTime, 'paid', 'ORD-SAME-123', 'PAY-456', 2999);
        $this->assertTrue($firstResult);

        $enr = Enrollment::where('user_id', $student->id)->where('course_id', $course->id)->first();
        $originalExpiry = $enr->expires_at;

        // Second retry webhook call with same order_id
        $secondResult = $service->assign($student, $course, null, now(), 'paid', 'ORD-SAME-123', 'PAY-456', 2999);
        $this->assertFalse($secondResult);

        $enr->refresh();
        $this->assertEquals($originalExpiry->toIso8601String(), $enr->expires_at->toIso8601String());
    }

    public function test_question_report_single_and_bulk_resolution_with_notifications(): void
    {
        Notification::fake();

        $admin = Admin::factory()->create(['role' => 'admin', 'email' => 'admin.reports@example.com']);
        $student = User::factory()->create(['email' => 'student.report@example.com', 'name' => 'John Cadet']);

        $test = Test::factory()->create(['title' => 'Meteorology Test']);
        $question = Question::factory()->create(['body' => 'What is an isobar?']);
        $attempt = TestAttempt::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'user_id' => $student->id,
            'test_id' => $test->id,
            'score' => 10,
            'started_at' => now()->subMinutes(30),
            'submitted_at' => now()->subMinutes(10),
        ]);

        $reportService = app(QuestionReportService::class);

        // Create report
        \Illuminate\Support\Facades\DB::table('test_questions')->insert(['test_id' => $test->id, 'question_id' => $question->id, 'order' => 1]);
        $report = $reportService->report($student, $attempt, $question->id, ReportReason::WrongAnswer, 'Option B is correct, not A');

        // Close report with email
        $reportService->close($report, ReportStatus::Resolved, 'Updated answer key to B', $admin, sendEmail: true);

        Notification::assertSentTo($student, QuestionReportClosed::class);

        // Test bulk close
        $question2 = Question::factory()->create(['body' => 'What causes trade winds?']);
        \Illuminate\Support\Facades\DB::table('test_questions')->insert(['test_id' => $test->id, 'question_id' => $question2->id, 'order' => 2]);
        $report2 = $reportService->report($student, $attempt, $question2->id, ReportReason::Typo, 'Typo in question stem');

        $reportService->bulkClose(collect([$report2]), ReportStatus::Resolved, 'Typo fixed', $admin, sendEmail: true);

        Notification::assertSentTo($student, BulkQuestionReportsClosed::class);
    }
}

