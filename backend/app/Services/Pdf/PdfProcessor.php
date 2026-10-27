<?php

namespace App\Services\Pdf;

use App\Models\PdfDocument;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Turns an uploaded PDF into page images (pdf-pages/{id}/1.png, 2.png…) on the PRIVATE disk.
 * Students are only ever served these images (with their name burned in), never the PDF.
 *
 * Renderer, in order of preference:
 *   1. poppler's `pdftoppm`  (Linux: apt install poppler-utils; or set LMS_PDFTOPPM)
 *   2. PyMuPDF via tools/pdf_tool.py  (pip install pymupdf)
 */
class PdfProcessor
{
    /**
     * 200 gives A4 pages about 1650px wide: sharp text on a phone (3x screens need ~1100px) and still readable when
     * zoomed to 200%. The old 110 stretched a 910px image over the screen, which looked soft and grey.
     */
    public const DPI = 200;

    public function __construct(private PdfTool $tool) {}

    public function process(PdfDocument $document): void
    {
        $disk = Storage::disk('local');
        abort_unless($disk->exists($document->file_path), 404, 'PDF file is missing.');

        // Render into a temporary folder and swap it in, so a student who is reading the document never meets
        // missing pages while it is being rendered again.
        $dir = "pdf-pages/{$document->id}";
        $tmp = "{$dir}.tmp";
        $disk->deleteDirectory($tmp);
        $disk->makeDirectory($tmp);

        $count = $this->hasPdftoppm()
            ? $this->renderWithPoppler($disk->path($document->file_path), $disk->path($tmp))
            : $this->tool->render($disk->path($document->file_path), $disk->path($tmp), self::DPI)['pageCount'];

        $disk->deleteDirectory($dir);
        rename($disk->path($tmp), $disk->path($dir));

        $document->update(['page_count' => $count, 'processed_at' => now()]);
    }

    /** Path of one page image on the private disk, or null if it doesn't exist. */
    public function pagePath(PdfDocument $document, int $page): ?string
    {
        $path = "pdf-pages/{$document->id}/{$page}.png";

        return Storage::disk('local')->exists($path) ? Storage::disk('local')->path($path) : null;
    }

    private function hasPdftoppm(): bool
    {
        return (bool) (new ExecutableFinder)->find(config('lms.pdftoppm')) || is_file(config('lms.pdftoppm'));
    }

    private function renderWithPoppler(string $pdf, string $dir): int
    {
        $process = new Process([config('lms.pdftoppm'), '-png', '-r', (string) self::DPI, $pdf, $dir.DIRECTORY_SEPARATOR.'page']);
        $process->setTimeout(300);
        $process->run();
        if (! $process->isSuccessful()) {
            throw new RuntimeException('pdftoppm failed: '.$process->getErrorOutput());
        }

        // pdftoppm names files page-1.png, page-01.png… → rename to 1.png, 2.png…
        $files = glob($dir.DIRECTORY_SEPARATOR.'page-*.png');
        natsort($files);
        foreach (array_values($files) as $i => $file) {
            rename($file, $dir.DIRECTORY_SEPARATOR.($i + 1).'.png');
        }

        return count($files);
    }
}
