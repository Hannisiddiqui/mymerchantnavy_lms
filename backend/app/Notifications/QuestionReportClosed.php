<?php

namespace App\Notifications;

use App\Enums\ReportStatus;
use App\Models\QuestionReport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** In-app and email notification to the student when an admin closes their question report. */
class QuestionReportClosed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private QuestionReport $report,
        private bool $sendEmail = true
    ) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];
        if ($this->sendEmail && filled($notifiable->routeNotificationForMail())) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $test = $this->report->test?->title ?? 'your practice test';
        $fixed = $this->report->status === ReportStatus::Resolved;
        $subject = $fixed ? 'Your Question Report Was Resolved — My Merchant Navy' : 'Update on Your Question Report — My Merchant Navy';

        $body = $this->report->admin_note
            ? "{$this->report->admin_note}"
            : ($fixed ? "The question in {$test} has been reviewed and corrected in the LMS." : "The question in {$test} was reviewed and is verified correct.");

        return (new MailMessage)
            ->subject($subject)
            ->greeting("Hello {$notifiable->name},")
            ->line("Thank you for submitting a report regarding Question #{$this->report->question_id} in {$test}.")
            ->line("Status: " . ($fixed ? 'Resolved & Corrected' : 'Reviewed (No Change Needed)'))
            ->line("Instructor / Admin Feedback:")
            ->line($body)
            ->action('View Your Test Results', url(config('lms.student_url', 'https://lms.mymerchantnavy.com') . ($this->report->attempt ? "/results/{$this->report->attempt->uuid}" : '')))
            ->line('Thank you for helping us maintain the highest academic standards.');
    }

    public function toArray(object $notifiable): array
    {
        $test = $this->report->test?->title ?? 'a test';
        $fixed = $this->report->status === ReportStatus::Resolved;

        return [
            'type' => 'report',
            'title' => $fixed ? 'Thanks! The question you reported was fixed' : 'We checked the question you reported',
            'body' => $this->report->admin_note
                ? "{$test}: {$this->report->admin_note}"
                : ($fixed ? "{$test}. The correction is live now." : "{$test}. The question is correct as it is."),
            'href' => $this->report->attempt ? "/results/{$this->report->attempt->uuid}" : null,
        ];
    }
}
