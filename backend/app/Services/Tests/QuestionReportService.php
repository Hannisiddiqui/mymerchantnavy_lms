<?php

namespace App\Services\Tests;

use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Models\ActivityLog;
use App\Models\Admin;
use App\Models\QuestionReport;
use App\Models\TestAttempt;
use App\Models\User;
use App\Notifications\BulkQuestionReportsClosed;
use App\Notifications\QuestionReportClosed;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Students report problems with a question from their result page; admins close them in Reports → Question reports.
 * A student has at most one OPEN report per question: reporting again updates it instead of adding a duplicate.
 */
class QuestionReportService
{
    public function report(User $user, TestAttempt $attempt, int $questionId, ReportReason $reason, ?string $details): QuestionReport
    {
        abort_unless($attempt->user_id === $user->id && $attempt->isFinished(), 404);
        abort_unless(DB::table('test_questions')->where('test_id', $attempt->test_id)->where('question_id', $questionId)->exists(), 404);

        $report = QuestionReport::firstOrNew([
            'user_id' => $user->id,
            'question_id' => $questionId,
            'status' => ReportStatus::Open,
        ]);
        $report->fill([
            'test_id' => $attempt->test_id,
            'test_attempt_id' => $attempt->id,
            'reason' => $reason,
            'details' => filled($details) ? trim($details) : null,
        ])->save();

        return $report;
    }

    /** Resolved or "no change needed". The student gets a note in the app and optionally an email via Brevo. */
    public function close(QuestionReport $report, ReportStatus $status, ?string $note, ?Admin $admin, bool $sendEmail = true): void
    {
        $report->update([
            'status' => $status,
            'admin_note' => filled($note) ? trim($note) : null,
            'resolved_by' => $admin?->id,
            'resolved_at' => now(),
        ]);

        $report->loadMissing(['test', 'attempt', 'user']);

        if ($report->user) {
            $report->user->notify(new QuestionReportClosed($report, $sendEmail));
        }

        ActivityLog::record(
            event: 'question_report.closed',
            description: "Admin {$admin?->email} marked question report #{$report->id} (Q #{$report->question_id}) as {$status->value}" . ($sendEmail ? ' and emailed student.' : '.'),
            subject: $report,
            properties: [
                'report_id' => $report->id,
                'question_id' => $report->question_id,
                'status' => $status->value,
                'email_sent' => $sendEmail,
                'student_email' => $report->user?->email,
            ],
            admin: $admin
        );
    }

    /** Bulk resolve multiple reports with consolidated email notifications per student */
    public function bulkClose(Collection $reports, ReportStatus $status, ?string $note, ?Admin $admin, bool $sendEmail = true): int
    {
        $openReports = $reports->where('status', ReportStatus::Open);
        $count = 0;

        // Group by user so students receive 1 consolidated email instead of 10 separate emails
        $byUser = $openReports->groupBy('user_id');

        foreach ($byUser as $userId => $userReports) {
            $user = User::find($userId);

            foreach ($userReports as $report) {
                $report->update([
                    'status' => $status,
                    'admin_note' => filled($note) ? trim($note) : null,
                    'resolved_by' => $admin?->id,
                    'resolved_at' => now(),
                ]);
                $count++;
            }

            if ($user && $sendEmail) {
                $user->notify(new BulkQuestionReportsClosed($userReports, $note));
            }
        }

        ActivityLog::record(
            event: 'question_report.bulk_resolved',
            description: "Admin {$admin?->email} bulk resolved {$count} question reports" . ($sendEmail ? ' and sent consolidated email updates.' : '.'),
            properties: [
                'resolved_count' => $count,
                'student_count' => $byUser->count(),
                'email_sent' => $sendEmail,
            ],
            admin: $admin
        );

        return $count;
    }

    public function reopen(QuestionReport $report): void
    {
        $report->update(['status' => ReportStatus::Open, 'resolved_by' => null, 'resolved_at' => null]);
    }
}
