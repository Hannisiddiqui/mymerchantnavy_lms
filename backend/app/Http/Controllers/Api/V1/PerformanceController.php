<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Student\PerformanceReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The student's performance dashboard (subject-wise and chapter-wise). */
class PerformanceController extends Controller
{
    /** GET /api/v1/performance */
    public function show(Request $request): JsonResponse
    {
        $report = new PerformanceReport($request->user(), $this->catalog($request));

        return $this->data($report->build());
    }
}
