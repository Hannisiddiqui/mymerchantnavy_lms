<?php

namespace App\Services\Otp;

/**
 * Delivers a login code: by email, or by SMS (one implementation per provider).
 * The active one is chosen by LMS_LOGIN_METHOD + OTP_EMAIL_DRIVER / OTP_DRIVER (see config/otp.php).
 */
interface OtpSender
{
    /**
     * @param  string  $to  an email address, or a 10-digit Indian mobile number such as 9876543210
     * @param  string|null  $code  the bare code, for senders that lay it out themselves (the email)
     */
    public function send(string $to, string $message, ?string $code = null): void;
}
