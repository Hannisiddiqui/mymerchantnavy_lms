<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BatchController extends Controller
{
    /**
     * Get batch store display settings and custom categories.
     */
    public function config(): JsonResponse
    {
        $showCategories = \App\Models\Setting::get('batch_page_show_categories', '1') !== '0';
        $savedCats = \App\Models\Setting::get('batch_custom_categories');
        $categories = $savedCats ? json_decode($savedCats, true) : null;
        if (! is_array($categories) || empty($categories)) {
            $categories = ['All Batches', 'IMU-CET', 'DNS Sponsorship', 'G.P. Rating', 'B.Tech Marine'];
        }

        return response()->json([
            'data' => [
                'show_categories' => $showCategories,
                'categories' => $categories,
            ],
        ]);
    }

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
            $cleanExam = trim((string) $targetExam);
            if (! in_array(strtolower($cleanExam), ['all', 'all batches', ''], true)) {
                $query->whereRaw('LOWER(target_exam) = ?', [strtolower($cleanExam)]);
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

