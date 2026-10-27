<?php

namespace App\Models;

use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A problem a student reported on one question, from the result page. */
class QuestionReport extends Model
{
    protected $fillable = ['user_id', 'question_id', 'test_id', 'test_attempt_id', 'reason', 'details', 'status', 'admin_note', 'resolved_by', 'resolved_at'];

    protected function casts(): array
    {
        return [
            'reason' => ReportReason::class,
            'status' => ReportStatus::class,
            'resolved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    /** Every report (by any student) on the same question. */
    public function sameQuestion(): HasMany
    {
        return $this->hasMany(self::class, 'question_id', 'question_id');
    }

    public function test(): BelongsTo
    {
        return $this->belongsTo(Test::class);
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(TestAttempt::class, 'test_attempt_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'resolved_by');
    }

    /** Shape used by the student app (`QuestionReportInfo` in student-app/src/types/index.ts). */
    public function toStudentArray(): array
    {
        return [
            'id' => $this->id,
            'reason' => $this->reason->value,
            'details' => $this->details ?? '',
            'status' => $this->status->value,
            'adminNote' => $this->admin_note ?? '',
            'createdAt' => $this->created_at->toIso8601String(),
        ];
    }
}
