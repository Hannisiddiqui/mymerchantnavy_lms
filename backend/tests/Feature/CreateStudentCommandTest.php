<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateStudentCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_a_student_by_email_with_a_completed_profile_and_assigns_all_courses(): void
    {
        $course = Course::create(['title' => 'Demo', 'slug' => 'demo', 'description' => 'x']);

        $this->artisan('lms:create-student', ['login' => 'Test@Example.com', '--name' => 'Test Student', '--all-courses' => true])
            ->assertSuccessful();

        $student = User::where('email', 'test@example.com')->firstOrFail();
        $this->assertFalse($student->needsProfile());
        $this->assertNotNull($student->email_verified_at);
        $this->assertSame('Test Student', $student->name);
        $this->assertTrue($student->courses()->whereKey($course->id)->exists());

        // Running it again updates instead of failing.
        $this->artisan('lms:create-student', ['login' => 'test@example.com'])->assertSuccessful();
        $this->assertSame(1, User::where('email', 'test@example.com')->count());
    }

    public function test_still_accepts_a_mobile_number_for_the_phone_login(): void
    {
        $this->artisan('lms:create-student', ['login' => '+91 99999 00001'])->assertSuccessful();

        $this->assertSame('Test Student', User::where('phone', '9999900001')->firstOrFail()->name);
    }

    public function test_rejects_a_login_that_is_neither_an_email_nor_a_phone_number(): void
    {
        $this->artisan('lms:create-student', ['login' => '123'])->assertFailed();
        $this->artisan('lms:create-student', ['login' => 'not@valid'])->assertFailed();
    }
}
