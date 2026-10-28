<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Email (through the same Brevo mail setup as every other email) when a course's validity rule or its existing enrollments change. */
class CourseValidityChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private string $subject, private array $lines) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject($this->subject)->greeting('Course validity update');
        foreach ($this->lines as $line) {
            $mail->line($line);
        }

        return $mail;
    }
}
