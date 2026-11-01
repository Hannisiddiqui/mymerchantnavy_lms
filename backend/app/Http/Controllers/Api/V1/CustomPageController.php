<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\CustomPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomPageController extends Controller
{
    /** Published pages the visitor may open (sidebar and directory). */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user('sanctum');

        $pages = CustomPage::live()
            ->orderBy('sort_order', 'asc')
            ->orderBy('id', 'asc')
            ->get(['id', 'title', 'slug', 'icon', 'badge_text', 'show_in_sidebar', 'sort_order', 'access', 'access_course_ids'])
            ->filter(fn (CustomPage $p) => $this->mayOpen($p, $user))
            ->map(fn (CustomPage $p) => $p->only(['id', 'title', 'slug', 'icon', 'badge_text', 'show_in_sidebar', 'sort_order']))
            ->values();

        return response()->json(['data' => $pages]);
    }

    /** Full content and builder blocks of one page, if the visitor is allowed to see it. */
    public function show(Request $request, string $slug): JsonResponse
    {
        $page = CustomPage::live()
            ->where(function ($q) use ($slug) {
                $q->where('slug', $slug);
                if (is_numeric($slug)) {
                    $q->orWhere('id', (int) $slug);
                }
            })
            ->firstOrFail();

        $user = $request->user('sanctum');
        abort_unless($this->mayOpen($page, $user), $user ? 403 : 401, $user ? 'This page is only for students of selected courses.' : 'Please log in to open this page.');

        $page->blocks = $this->withBatches($page->blocks);

        return response()->json(['data' => $page->makeHidden(['access_course_ids'])]);
    }

    /** public = anyone, students = any logged-in student, courses = students with a valid enrollment in one of the chosen courses. */
    private function mayOpen(CustomPage $page, $user): bool
    {
        if ($page->access === 'public') {
            return true;
        }
        if (! $user) {
            return false;
        }
        if ($page->access === 'courses') {
            $ids = array_map('intval', (array) $page->access_course_ids);

            return $ids && $user->enrollments()->whereIn('course_id', $ids)->active()->exists();
        }

        return true;
    }

    /** Fills "batches_showcase" sections with the live batches the admin picked. */
    private function withBatches(?array $blocks): ?array
    {
        if (! is_array($blocks)) {
            return $blocks;
        }
        foreach ($blocks as $i => $block) {
            if (($block['type'] ?? null) === 'batches_showcase') {
                $ids = array_map('intval', (array) ($block['data']['batch_ids'] ?? []));
                $batches = Batch::whereIn('id', $ids ?: [0])->where('is_live', true)
                    ->get(['id', 'title', 'slug', 'image_url', 'price', 'mrp_price', 'discount_percent', 'validity_text', 'purchase_url', 'highlights', 'footer_tag'])
                    ->sortBy(fn ($b) => array_search($b->id, $ids))->values();
                $blocks[$i]['data']['batches'] = $batches;
            }
        }

        return $blocks;
    }
}
