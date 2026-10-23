<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** The email that carries a student's one-time login code. */
class LoginCodeMail extends Mailable
{
    public function __construct(public string $code, public int $minutes) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->code.' is your My Merchant Navy login code');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.login-code');
    }
}
