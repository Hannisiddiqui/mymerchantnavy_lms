<?php

namespace App\Services\Otp;

use Illuminate\Support\Facades\Log;

/** Local development: the message (with the code) is written to storage/logs/laravel.log. */
class LogSender implements OtpSender
{
    public function send(string $to, string $message, ?string $code = null): void
    {
        Log::info("[OTP] to {$to}: {$message}");
    }
}
