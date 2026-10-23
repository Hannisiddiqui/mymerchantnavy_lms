<?php

namespace App\Services\Otp;

use App\Mail\LoginCodeMail;
use Illuminate\Support\Facades\Mail;

/**
 * Emails the login code through whatever MAIL_* settings are configured: Brevo SMTP for the pilot
 * (MAIL_HOST=smtp-relay.brevo.com, MAIL_PORT=587, MAIL_USERNAME / MAIL_PASSWORD = the Brevo SMTP login and key).
 */
class MailSender implements OtpSender
{
    public function send(string $to, string $message, ?string $code = null): void
    {
        Mail::to($to)->send(new LoginCodeMail($code ?? '', (int) config('otp.ttl_minutes')));
    }
}
