<?php

namespace Tests\Feature;

use App\Mail\LoginCodeMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** The login code really goes out as an email (through whatever MAIL_* settings, e.g. Brevo). */
class LoginEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_code_is_emailed_to_the_student(): void
    {
        Mail::fake();

        $this->postJson('/api/v1/auth/otp/send', ['email' => 'Aarav@Example.com'])->assertOk()->assertJsonPath('message', 'Code emailed');

        Mail::assertSent(LoginCodeMail::class, function (LoginCodeMail $mail) {
            return $mail->hasTo('aarav@example.com') && preg_match('/^\d{6}$/', $mail->code) === 1 && $mail->minutes === 10;
        });
    }

    public function test_the_emailed_code_logs_the_student_in(): void
    {
        Mail::fake();
        $this->postJson('/api/v1/auth/otp/send', ['email' => 'aarav@example.com'])->assertOk();
        $code = Mail::sent(LoginCodeMail::class)->first()->code;

        $this->postJson('/api/v1/auth/otp/verify', ['email' => 'aarav@example.com', 'code' => $code])
            ->assertOk()->assertJsonPath('data.needsProfile', true);
    }

    public function test_the_email_shows_the_code_prominently(): void
    {
        $html = (new LoginCodeMail('482913', 10))->render();

        $this->assertStringContainsString('482913', $html);
        $this->assertStringContainsString('10 minutes', $html);
    }

    public function test_codes_cannot_be_requested_again_straight_away(): void
    {
        Mail::fake();
        $this->postJson('/api/v1/auth/otp/send', ['email' => 'aarav@example.com'])->assertOk();

        $this->postJson('/api/v1/auth/otp/send', ['email' => 'AARAV@example.com'])->assertJsonValidationErrors('email');
        Mail::assertSentCount(1);
    }
}
