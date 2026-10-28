<?php

namespace App\Notifications;

use App\Models\Course;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** A course was assigned, bought or renewed. In-app bell + email (when the student has an email). */
class CourseAssigned extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private Course $course, private ?CarbonInterface $expiresAt = null, private bool $renewed = false) {}

    public function via(object $notifiable): array
    {
        return filled($notifiable->email) ? ['database', 'mail'] : ['database'];
    }

    /** Shape matches `AppNotification` in student-app/src/types/index.ts */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'course_assigned',
            'title' => $this->renewed ? 'Course access renewed' : 'New course assigned',
            'body' => ($this->renewed ? "Your access to {$this->course->title} was renewed" : "You now have access to {$this->course->title}")
                .($this->expiresAt ? ' until '.$this->expiresAt->format('d M Y').'.' : '.'),
            'href' => "/my-courses/{$this->course->id}",
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->renewed ? "Your access to {$this->course->title} was renewed" : "You now have access to {$this->course->title}")
            ->greeting('Hi '.($notifiable->nickname ?: 'there').',')
            ->line("Your course “{$this->course->title}” is ready on your dashboard.");

        if ($this->expiresAt) {
            $mail->line('Your access is valid until '.$this->expiresAt->format('d F Y').'.');
        }

        return $mail->action('Start learning', config('lms.student_app_url').'/my-courses/'.$this->course->id);
    }
}
