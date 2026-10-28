<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * Consolidated email report sent to a student when multiple question reports are reviewed/resolved in bulk.
 */
class BulkQuestionReportsClosed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private Collection $reports,
        private ?string $adminNote = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $count = $this->reports->count();
        $mail = (new MailMessage)
            ->subject("Update on your {$count} reported questions — My Merchant Navy")
            ->greeting("Hello {$notifiable->name},")
            ->line("Our academic team has reviewed {$count} question reports submitted by your account.")
            ->line('Summary of Reviewed Questions:');

        foreach ($this->reports as $report) {
            $testTitle = $report->test?->title ?? 'Test';
            $mail->line("• Question #{$report->question_id} ({$testTitle}): Resolved & updated in the question bank.");
        }

        if ($this->adminNote) {
            $mail->line('Instructor Note:')
                ->line($this->adminNote);
        }

        $mail->action('Go to Student LMS', config('lms.student_url', 'https://lms.mymerchantnavy.com'))
            ->line('Keep practicing and thank you for helping us keep question content accurate!');

        return $mail;
    }

    public function toArray(object $notifiable): array
    {
        $count = $this->reports->count();

        return [
            'type' => 'report_bulk',
            'title' => "{$count} reported questions have been reviewed and resolved",
            'body' => $this->adminNote ?: "All {$count} questions you reported have been verified and updated in the system.",
            'href' => '/dashboard',
        ];
    }
}

