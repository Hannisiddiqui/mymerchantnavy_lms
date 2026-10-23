<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ContentType;
use App\Http\Controllers\Controller;
use App\Models\ChapterContent;
use App\Models\User;
use App\Services\Pdf\PdfProcessor;
use App\Services\Pdf\WatermarkService;
use App\Services\Student\StudentCatalog;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Serves ONE page of a protected PDF as a watermarked image.
 * The link is signed (see ContentController::pages), expires quickly and carries the student's id,
 * because <img> tags cannot send the login token. Access is re-checked on every request.
 */
class PdfPageController extends Controller
{
    public function show(Request $request, int $content, int $page, PdfProcessor $pages, WatermarkService $watermark): Response
    {
        $student = User::where('is_active', true)->findOrFail($request->query('u'));
        $item = app(StudentCatalog::class, ['user' => $student])->findContent($content); // 404 if access was removed

        abort_unless($item->type === ContentType::Pdf, 404);

        $path = $pages->pagePath($item->contentable, $page);
        abort_unless($path, 404);

        $png = $watermark->apply($path, trim("{$student->name} · {$student->loginId()}", ' ·'));

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
