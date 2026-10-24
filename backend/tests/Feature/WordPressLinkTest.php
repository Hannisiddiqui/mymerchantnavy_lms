<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\User;
use App\Services\WordPress\WordPressLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class WordPressLinkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['lms.wordpress.secret' => 'test-secret-test-secret-test-secret']);
    }

    /** A signed message as the WordPress plugin sends it. */
    private function signed(string $path, array $body, ?int $time = null, ?string $secret = null)
    {
        $json = json_encode($body);
        $time ??= time();
        $sig = hash_hmac('sha256', "{$time}.{$json}", $secret ?? config('lms.wordpress.secret'));

        return $this->call('POST', "/api/v1/integrations/wordpress/{$path}", [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_MMN_TIMESTAMP' => (string) $time, 'HTTP_X_MMN_SIGNATURE' => $sig,
        ], $json);
    }

    public function test_registering_on_wordpress_creates_a_student_without_courses(): void
    {
        Course::create(['title' => 'C', 'slug' => 'c', 'wordpress_product_id' => '10']);

        $this->signed('student', ['email' => 'Riya@Example.com', 'name' => 'Riya Shah', 'phone' => '+91 98765 43210', 'wp_user_id' => 77])
            ->assertOk()->assertJsonPath('data.created', true);

        $student = User::where('email', 'riya@example.com')->firstOrFail();
        $this->assertSame('Riya Shah', $student->name);
        $this->assertSame('9876543210', $student->phone);
        $this->assertSame(77, $student->wordpress_user_id);
        $this->assertSame(0, $student->courses()->count());

        // sending it again does not create a second student
        $this->signed('student', ['email' => 'riya@example.com'])->assertOk()->assertJsonPath('data.created', false);
        $this->assertSame(1, User::count());
    }

    public function test_payment_gives_the_mapped_course_once(): void
    {
        Notification::fake();
        $course = Course::create(['title' => 'IMU-CET', 'slug' => 'imu', 'wordpress_product_id' => '10, 11']);
        Course::create(['title' => 'Other', 'slug' => 'other', 'wordpress_product_id' => '99']);

        $this->signed('enroll', ['email' => 'a@example.com', 'name' => 'A', 'product_id' => '11', 'order_id' => 'order_1'])
            ->assertOk()->assertJsonPath('data.newCourses', ['IMU-CET']);
        $this->signed('enroll', ['email' => 'a@example.com', 'product_id' => '11'])->assertOk()->assertJsonPath('data.newCourses', []);

        $this->assertSame([$course->id], User::where('email', 'a@example.com')->firstOrFail()->courses()->pluck('courses.id')->all());
    }

    public function test_unknown_product_is_reported_and_no_student_is_made(): void
    {
        $this->signed('enroll', ['email' => 'a@example.com', 'product_id' => '404'])->assertStatus(422);
        $this->assertSame(0, User::count());
    }

    public function test_unsigned_old_or_wrongly_signed_messages_are_refused(): void
    {
        $this->postJson('/api/v1/integrations/wordpress/student', ['email' => 'a@example.com'])->assertStatus(401);
        $this->signed('student', ['email' => 'a@example.com'], secret: 'someone-elses-secret')->assertStatus(401);
        $this->signed('student', ['email' => 'a@example.com'], time() - 3600)->assertStatus(401);
        $this->assertSame(0, User::count());

        config(['lms.wordpress.secret' => null]);
        $this->signed('student', ['email' => 'a@example.com'], secret: 'x')->assertStatus(503);
    }

    public function test_the_sign_in_link_logs_the_student_in_once(): void
    {
        $link = app(WordPressLink::class);
        $token = $link->makeToken('Paid@Example.com');

        $response = $this->postJson('/api/v1/auth/sso', ['token' => $token])->assertOk();
        $this->assertNotEmpty($response->json('data.token'));
        $this->assertTrue($response->json('data.needsProfile'));
        $this->assertSame(1, User::where('email', 'paid@example.com')->count());

        // the same link cannot be used again
        $this->postJson('/api/v1/auth/sso', ['token' => $token])->assertStatus(401);
        // the token it returned really works
        $this->withToken($response->json('data.token'))->getJson('/api/v1/me')->assertOk();
    }

    public function test_forged_or_expired_sign_in_links_are_refused(): void
    {
        $link = app(WordPressLink::class);

        $this->postJson('/api/v1/auth/sso', ['token' => $link->makeToken('a@example.com', time() - 10)])->assertStatus(401);
        $this->postJson('/api/v1/auth/sso', ['token' => $link->makeToken('a@example.com', time() + 86400)])->assertStatus(401);
        [$payload] = explode('.', $link->makeToken('a@example.com'));
        $this->postJson('/api/v1/auth/sso', ['token' => $payload.'.'.str_repeat('0', 64)])->assertStatus(401);
        $this->postJson('/api/v1/auth/sso', ['token' => 'garbage'])->assertStatus(401);
        $this->assertSame(0, User::count());
    }

    public function test_a_disabled_student_cannot_use_the_link(): void
    {
        User::create(['email' => 'off@example.com', 'is_active' => false]);

        $this->postJson('/api/v1/auth/sso', ['token' => app(WordPressLink::class)->makeToken('off@example.com')])->assertStatus(403);
    }

    public function test_the_link_never_signs_in_an_account_whose_owner_has_verified_the_email(): void
    {
        // WordPress does not verify emails: someone could register there with a victim's address and pay.
        User::create(['email' => 'victim@example.com', 'name' => 'Victim', 'email_verified_at' => now()]);

        $this->postJson('/api/v1/auth/sso', ['token' => app(WordPressLink::class)->makeToken('victim@example.com')])->assertStatus(401);
    }

    public function test_a_link_from_a_wordpress_account_with_a_verified_email_signs_in(): void
    {
        User::create(['email' => 'real@example.com', 'name' => 'Real', 'email_verified_at' => now()]);

        // WordPress emailed a code to this address and the student typed it back
        $this->postJson('/api/v1/auth/sso', ['token' => app(WordPressLink::class)->makeToken('real@example.com', verified: true)])->assertOk();
    }

    public function test_wordpress_telling_us_the_email_is_verified_marks_the_student_verified(): void
    {
        $this->signed('student', ['email' => 'v@example.com', 'email_verified' => true])->assertOk();
        $this->signed('student', ['email' => 'u@example.com', 'email_verified' => false])->assertOk();

        $this->assertNotNull(User::where('email', 'v@example.com')->value('email_verified_at'));
        $this->assertNull(User::where('email', 'u@example.com')->value('email_verified_at'));
        // and an unverified message never takes verification away
        $this->signed('student', ['email' => 'v@example.com'])->assertOk();
        $this->assertNotNull(User::where('email', 'v@example.com')->value('email_verified_at'));
    }

    public function test_proving_the_email_ends_sessions_made_before_it(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        // a session made from the WordPress link (the address was never proved)
        $linkToken = $this->postJson('/api/v1/auth/sso', ['token' => app(WordPressLink::class)->makeToken('owner@example.com')])->json('data.token');
        $this->withToken($linkToken)->getJson('/api/v1/me')->assertOk();
        $this->app['auth']->forgetGuards();

        // the real owner logs in with the emailed code
        $this->postJson('/api/v1/auth/otp/send', ['email' => 'owner@example.com'])->assertOk();
        $code = \Illuminate\Support\Facades\Mail::sent(\App\Mail\LoginCodeMail::class)->first()->code;
        $this->postJson('/api/v1/auth/otp/verify', ['email' => 'owner@example.com', 'code' => $code])->assertOk();

        $this->app['auth']->forgetGuards();
        $this->withToken($linkToken)->getJson('/api/v1/me')->assertStatus(401);
        $this->assertCount(1, User::where('email', 'owner@example.com')->firstOrFail()->tokens);
    }
}
