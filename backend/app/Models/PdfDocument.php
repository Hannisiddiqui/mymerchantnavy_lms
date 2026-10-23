<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/** Stored on the private disk. Students only ever receive watermarked page images. */
class PdfDocument extends Model
{
    protected $fillable = ['file_path', 'page_count', 'processed_at'];

    protected function casts(): array
    {
        return ['processed_at' => 'datetime'];
    }

    /** Deleting a PDF (or replacing its file) also removes the file and its page images from the server. */
    protected static function booted(): void
    {
        static::deleted(function (self $pdf) {
            Storage::disk('local')->delete($pdf->file_path);
            Storage::disk('local')->deleteDirectory("pdf-pages/{$pdf->id}");
        });
        static::updated(function (self $pdf) {
            $old = $pdf->getOriginal('file_path');
            if ($pdf->wasChanged('file_path') && $old) {
                Storage::disk('local')->delete($old);
            }
        });
    }
}
