<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BatchController extends Controller
{
    /**
     * List all live batches for student LMS.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user('sanctum');

        $query = Batch::where('is_live', true)
            ->with(['courses:id,title,slug,thumbnail_path', 'tests:id,title,duration_minutes'])
            ->orderBy('sort_order', 'asc')
            ->orderBy('id', 'desc');

        if ($targetExam = $request->query('target_exam')) {
            if ($targetExam !== 'all' && $targetExam !== 'All Batches') {
                $query->where('target_exam', $targetExam);
            }
        }

        $batches = $query->get()->map(function (Batch $batch) use ($user) {
            return $this->formatBatch($batch, $user);
        });

        return response()->json(['data' => $batches]);
    }

    /**
     * Show a single batch by slug.
     */
    public function show(string $slug, Request $request): JsonResponse
    {
        $user = $request->user('sanctum');

        $batch = Batch::where('is_live', true)
            ->where(function ($q) use ($slug) {
                $q->where('slug', $slug);
                if (is_numeric($slug)) {
                    $q->orWhere('id', (int) $slug);
                }
            })
            ->with(['courses:id,title,slug,thumbnail_path', 'tests:id,title,duration_minutes'])
            ->firstOrFail();

        return response()->json(['data' => $this->formatBatch($batch, $user)]);
    }

    /**
     * Helper to format batch data consistently.
     */
    protected function formatBatch(Batch $batch, $user): array
    {
        $isEnrolled = $batch->isEnrolledByUser($user);

        return [
            'id' => $batch->id,
            'title' => $batch->title,
            'slug' => $batch->slug,
            'batchCode' => $batch->batch_code,
            'targetExam' => $batch->target_exam,
            'imageUrl' => $batch->image_url,
            'description' => $batch->description,
            'highlights' => $batch->highlights ?: [],
            'mrpPrice' => (float) $batch->mrp_price,
            'price' => (float) $batch->price,
            'discountPercent' => $batch->effective_discount,
            'startsAt' => $batch->starts_at?->format('Y-m-d'),
            'validityText' => $batch->validity_text ?: 'Validity: Till Exam',
            'endsAt' => $batch->ends_at?->format('Y-m-d'),
            'purchaseUrl' => $batch->purchase_url ?: 'https://mymerchantnavy.com/pass/',
            'wpProductId' => $batch->wp_product_id,
            'footerTag' => $batch->footer_tag ?: 'Free Test Series & Question Bank Included',
            'isLive' => $batch->is_live,
            'status' => $batch->status,
            'isEnrolled' => $isEnrolled,
            'courses' => $batch->courses->map(fn ($c) => [
                'id' => $c->id,
                'title' => $c->title,
                'slug' => $c->slug,
            ]),
            'tests' => $batch->tests->map(fn ($t) => [
                'id' => $t->id,
                'title' => $t->title,
                'durationMinutes' => $t->duration_minutes,
            ]),
        ];
    }
}

