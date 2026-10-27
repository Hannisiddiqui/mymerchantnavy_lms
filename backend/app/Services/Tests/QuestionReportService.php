<?php

namespace App\Services\Tests;

use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Models\Admin;
use App\Models\QuestionReport;
use App\Models\TestAttempt;
use App\Models\User;
use App\Notifications\QuestionReportClosed;
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

    /** Resolved or "no change needed". The student gets a note in the app with the admin's reply. */
    public function close(QuestionReport $report, ReportStatus $status, ?string $note, ?Admin $admin): void
    {
        $report->update([
            'status' => $status,
            'admin_note' => filled($note) ? trim($note) : null,
            'resolved_by' => $admin?->id,
            'resolved_at' => now(),
        ]);

        $report->user?->notify(new QuestionReportClosed($report->loadMissing('test', 'attempt')));
    }

    public function reopen(QuestionReport $report): void
    {
        $report->update(['status' => ReportStatus::Open, 'resolved_by' => null, 'resolved_at' => null]);
    }
}
