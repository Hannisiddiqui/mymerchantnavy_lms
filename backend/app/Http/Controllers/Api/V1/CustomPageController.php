<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CustomPage;
use Illuminate\Http\JsonResponse;

class CustomPageController extends Controller
{
    /**
     * List all published custom pages (used for sidebar navigation and directory).
     */
    public function index(): JsonResponse
    {
        $pages = CustomPage::where('is_published', true)
            ->orderBy('sort_order', 'asc')
            ->orderBy('id', 'asc')
            ->get([
                'id',
                'title',
                'slug',
                'icon',
                'badge_text',
                'show_in_sidebar',
                'sort_order',
            ]);

        return response()->json(['data' => $pages]);
    }

    /**
     * Show full content and builder blocks for a custom page.
     */
    public function show(string $slug): JsonResponse
    {
        $page = CustomPage::where('is_published', true)
            ->where(function ($q) use ($slug) {
                $q->where('slug', $slug);
                if (is_numeric($slug)) {
                    $q->orWhere('id', (int) $slug);
                }
            })
            ->firstOrFail();

        return response()->json(['data' => $page]);
    }
}
