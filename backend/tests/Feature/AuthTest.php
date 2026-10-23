<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Otp\OtpSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Login: email + emailed code (the default), the phone + SMS login (switched off but kept), sessions. */
class AuthTest extends TestCase
{
    use RefreshDatabase;

    /** Captures the message instead of sending it. */
    private object $outbox;

    protected function setUp(): void
    {
        parent::setUp();

        $this->outbox = new class implements OtpSender
        {
            public array $sent = [];

            public function send(string $to, string $message, ?string $code = null): void
            {
                $this->sent[$to] = $code ?? $message;
            }
        };
        $this->app->instance(OtpSender::class, $this->outbox);
    }

    private function codeSentTo(string $to): string
    {
        preg_match('/\b(\d{6})\b/', $this->outbox->sent[$to], $m);

        return $m[1];
    }

    /** Logs in with the active method. $who is an email, or a phone number when the phone login is on. */
    private function login(string $who, string $device = 'Test device'): array
    {
        $key = config('lms.login_method');
        $this->postJson('/api/v1/auth/otp/send', [$key => $who])->assertOk();
        $this->travel(31)->seconds(); // allow the next resend

        return $this->postJson('/api/v1/auth/otp/verify', [
            $key => $who,
            'code' => $this->codeSentTo(mb_strtolower($who)),
            'device_name' => $device,
        ])->assertOk()->json('data');
    }

    // ---------------------------------------------------------------- email login (default)

    public function test_email_is_the_default_way_to_log_in(): void
    {
        $this->assertSame('email', config('lms.login_method'));
    }

    public function test_new_student_logs_in_with_email_and_must_complete_profile(): void
    {
        $data = $this->login('Aarav@Example.com');

        $this->assertTrue($data['needsProfile']);
        $this->assertNotEmpty($data['token']);
        $student = User::firstWhere('email', 'aarav@example.com'); // stored in lower case
        $this->assertNotNull($student->email_verified_at, 'receiving the code verifies the email');
        $this->assertNull($student->phone);
    }

    public function test_an_account_made_by_an_admin_is_found_whatever_the_letter_case(): void
    {
        $existing = User::factory()->create(['email' => 'Riya@Example.com', 'email_verified_at' => null]);

        $data = $this->login('riya@example.com');

        $this->assertFalse($data['needsProfile']);
        $this->assertSame(1, User::count());
        $this->assertNotNull($existing->fresh()->email_verified_at);
    }

    public function test_wrong_code_is_rejected(): void
    {
        $this->postJson('/api/v1/auth/otp/send', ['email' => 'aarav@example.com'])->assertOk();

        $this->postJson('/api/v1/auth/otp/verify', ['email' => 'aarav@example.com', 'code' => '000000'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }

    public function test_a_code_for_one_email_does_not_work_for_another(): void
    {
        $this->postJson('/api/v1/auth/otp/send', ['email' => 'a@example.com'])->assertOk();
        $code = $this->codeSentTo('a@example.com');

        $this->postJson('/api/v1/auth/otp/verify', ['email' => 'b@example.com', 'code' => $code])->assertUnprocessable();
    }

    public function test_invalid_email_is_rejected(): void
    {
        $this->postJson('/api/v1/auth/otp/send', ['email' => 'not-an-email'])->assertJsonValidationErrors('email');
        $this->postJson('/api/v1/auth/otp/send', ['phone' => '9876543210'])->assertJsonValidationErrors('email');
    }

    public function test_profile_is_completed_with_a_locked_email_and_an_optional_phone(): void
    {
        $token = $this->login('aarav@example.com')['token'];

        $this->withToken($token)->putJson('/api/v1/me', [
            'name' => 'Aarav Sharma',
            'nickname' => 'Aarav',
            'email' => 'someone-else@example.com', // ignored: the verified login cannot be changed
            'phone' => '9876543210',
            'targetExam' => 'IMU-CET',
            'attemptYear' => now()->year + 1,
        ])->assertOk()
            ->assertJsonPath('data.email', 'aarav@example.com')
            ->assertJsonPath('data.phone', '9876543210')
            ->assertJsonPath('data.emailVerified', true);

        $this->assertFalse(User::firstWhere('email', 'aarav@example.com')->needsProfile());
    }

    public function test_a_phone_number_can_only_belong_to_one_account(): void
    {
        User::factory()->create(['phone' => '9876543210']);
        $token = $this->login('new@example.com')['token'];

        $this->withToken($token)->putJson('/api/v1/me', [
            'name' => 'New Student', 'nickname' => 'New', 'phone' => '9876543210', 'targetExam' => 'IMU-CET', 'attemptYear' => now()->year + 1,
        ])->assertJsonValidationErrors('phone');
    }

    // ---------------------------------------------------------------- staying logged in

    public function test_students_stay_logged_in_on_every_device_until_they_log_out_themselves(): void
    {
        $this->assertSame(0, config('lms.max_devices'));
        $this->assertNull(config('sanctum.expiration'), 'login tokens never expire');

        $tokens = [
            $this->login('aarav@example.com', 'Laptop')['token'],
            $this->login('aarav@example.com', 'Phone')['token'],
            $this->login('aarav@example.com', 'Tablet')['token'],
        ];
        $this->assertSame(3, User::firstWhere('email', 'aarav@example.com')->tokens()->count());

        // a month later every device is still logged in
        $this->travel(30)->days();
        foreach ($tokens as $token) {
            $this->app['auth']->forgetGuards();
            $this->withToken($token)->getJson('/api/v1/me')->assertOk();
        }

        // ...until the student logs a device out
        $this->app['auth']->forgetGuards();
        $this->withToken($tokens[0])->postJson('/api/v1/auth/logout')->assertOk();
        $this->app['auth']->forgetGuards();
        $this->withToken($tokens[0])->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_an_optional_device_limit_logs_out_the_oldest(): void
    {
        config(['lms.max_devices' => 2]);

        $first = $this->login('aarav@example.com', 'Laptop')['token'];
        $this->login('aarav@example.com', 'Phone');
        $third = $this->login('aarav@example.com', 'Tablet')['token'];

        $this->assertSame(2, User::firstWhere('email', 'aarav@example.com')->tokens()->count());
        $this->withToken($third)->getJson('/api/v1/me/devices')->assertOk()->assertJsonCount(2, 'data');

        $this->app['auth']->forgetGuards();
        $this->withToken($first)->getJson('/api/v1/me')->assertUnauthorized();
    }

    // ---------------------------------------------------------------- phone login (hidden, still works)

    public function test_the_phone_login_still_works_when_switched_on(): void
    {
        config(['lms.login_method' => 'phone']);

        $data = $this->login('9876543210');

        $this->assertTrue($data['needsProfile']);
        $this->assertDatabaseHas('users', ['phone' => '9876543210']);
        $this->postJson('/api/v1/auth/otp/send', ['phone' => '12345'])->assertJsonValidationErrors('phone');
        $this->postJson('/api/v1/auth/otp/send', ['email' => 'a@example.com'])->assertJsonValidationErrors('phone');
    }

    public function test_the_phone_profile_still_asks_for_an_email(): void
    {
        config(['lms.login_method' => 'phone']);
        $token = $this->login('9876543210')['token'];

        $this->withToken($token)->putJson('/api/v1/me', [
            'name' => 'Aarav Sharma', 'nickname' => 'Aarav', 'targetExam' => 'IMU-CET', 'attemptYear' => now()->year + 1,
        ])->assertJsonValidationErrors('email');
    }
}
