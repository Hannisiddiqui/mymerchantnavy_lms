<?php

namespace App\Services\Otp;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Twilio Programmable SMS (REST API, no SDK needed). Keys: TWILIO_SID, TWILIO_AUTH_TOKEN, TWILIO_FROM. */
class TwilioSender implements OtpSender
{
    public function send(string $to, string $message, ?string $code = null): void
    {
        $config = config('otp.twilio');

        if (! $config['sid'] || ! $config['token'] || ! $config['from']) {
            throw new RuntimeException('Twilio is not configured. Set TWILIO_SID, TWILIO_AUTH_TOKEN and TWILIO_FROM.');
        }

        Http::asForm()
            ->withBasicAuth($config['sid'], $config['token'])
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$config['sid']}/Messages.json", [
                'To' => config('lms.default_country_code').$to,
                'From' => $config['from'],
                'Body' => $message,
            ])
            ->throw();
    }
}
