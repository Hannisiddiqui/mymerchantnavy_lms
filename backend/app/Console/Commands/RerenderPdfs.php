<?php

namespace App\Console\Commands;

use App\Models\PdfDocument;
use App\Services\Pdf\PdfProcessor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Renders PDF documents to page images again, e.g. after the render quality (PdfProcessor::DPI) was raised.
 * Each document keeps serving its old pages until the new ones are ready.
 */
class RerenderPdfs extends Command
{
    protected $signature = 'pdf:rerender {--id=* : Only these PDF document ids} {--only-old : Skip documents whose pages are already sharp}';

    protected $description = 'Render PDF documents to page images again (sharper text)';

    public function handle(PdfProcessor $processor): int
    {
        $documents = PdfDocument::whereNotNull('processed_at')
            ->when($this->option('id'), fn ($q, $ids) => $q->whereKey($ids))
            ->orderBy('id')->get();

        $done = $failed = 0;
        foreach ($documents as $document) {
            // A page image narrower than ~1300px was made at the old quality
            if ($this->option('only-old') && ($size = @getimagesize(Storage::disk('local')->path("pdf-pages/{$document->id}/1.png"))) && $size[0] >= 1300) {
                continue;
            }

            try {
                $processor->process($document);
                $done++;
                $this->line("#{$document->id}: {$document->page_count} pages");
            } catch (\Throwable $e) {
                $failed++;
                $this->error("#{$document->id}: {$e->getMessage()}");
            }
        }

        $this->info("Re-rendered {$done} document(s), {$failed} failed.");

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
