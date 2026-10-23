<?php

/*
| OTP login settings.
| To switch SMS provider, set OTP_DRIVER in .env and fill that provider's keys.
| Adding a new provider = one class in app/Services/Otp implementing OtpSender
| + one line in the `drivers` map below.
*/

return [
    // Used when students log in by PHONE: twilio | log (log = writes the code to storage/logs, for local development)
    'driver' => env('OTP_DRIVER', 'log'),

    // Used when students log in by EMAIL: mail (sent with the MAIL_* settings, e.g. Brevo) | log (local development)
    'email_driver' => env('OTP_EMAIL_DRIVER', 'mail'),

    // DEVELOPMENT ONLY: when set, every OTP is this code (e.g. 123456). Ignored when APP_ENV=production.
    'fixed_code' => env('OTP_FIXED_CODE'),

    'length' => 6,
    'ttl_minutes' => 10,
    'max_attempts' => 5,          // wrong guesses allowed per code
    'resend_after_seconds' => 30, // must match OTP_RESEND_SECONDS in the student app

    'message' => 'Your My Merchant Navy login code is :code. It is valid for :minutes minutes. Do not share it with anyone.',

    'drivers' => [
        'log' => App\Services\Otp\LogSender::class,
        'mail' => App\Services\Otp\MailSender::class,
        'twilio' => App\Services\Otp\TwilioSender::class,
        // 'msg91' => App\Services\Otp\Msg91Sender::class,
        // 'fast2sms' => App\Services\Otp\Fast2SmsSender::class,
        // '2factor' => App\Services\Otp\TwoFactorSender::class,
    ],

    'twilio' => [
        'sid' => env('TWILIO_SID'),
        'token' => env('TWILIO_AUTH_TOKEN'),
        'from' => env('TWILIO_FROM'),
    ],
];
