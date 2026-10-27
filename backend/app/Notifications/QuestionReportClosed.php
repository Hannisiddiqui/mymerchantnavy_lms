<?php

namespace App\Notifications;

use App\Enums\ReportStatus;
use App\Models\QuestionReport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/** In-app note to the student when an admin closes the question report they sent. */
class QuestionReportClosed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private QuestionReport $report) {}

    public function via(object $notifiable): array
    {
        return ['database'];
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
