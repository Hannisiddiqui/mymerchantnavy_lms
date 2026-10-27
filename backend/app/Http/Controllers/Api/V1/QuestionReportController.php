<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ReportReason;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReportQuestionRequest;
use App\Services\Tests\QuestionReportService;
use Illuminate\Http\JsonResponse;

/** "Report a problem" on a question in the detailed solutions of a result. */
class QuestionReportController extends Controller
{
    public function __construct(private QuestionReportService $reports) {}

    /** POST /api/v1/attempts/{attempt}/questions/{question}/report */
    public function store(ReportQuestionRequest $request, string $attempt, int $question): JsonResponse
    {
        $attempt = $request->user()->attempts()->where('uuid', $attempt)->firstOrFail();
        $report = $this->reports->report($request->user(), $attempt, $question, ReportReason::from($request->validated('reason')), $request->validated('details'));

        return $this->data($report->toStudentArray(), 201);
    }
}
