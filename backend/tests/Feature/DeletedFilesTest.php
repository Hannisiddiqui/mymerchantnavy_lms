<?php

namespace Tests\Feature;

use App\Models\PdfDocument;
use App\Models\QuestionImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DeletedFilesTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_a_pdf_removes_its_file_and_page_images(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('pdfs/a.pdf', 'x');
        $pdf = PdfDocument::create(['file_path' => 'pdfs/a.pdf']);
        Storage::disk('local')->put("pdf-pages/{$pdf->id}/1.png", 'x');

        $pdf->delete();

        Storage::disk('local')->assertMissing('pdfs/a.pdf');
        Storage::disk('local')->assertMissing("pdf-pages/{$pdf->id}/1.png");
    }

    public function test_replacing_the_file_removes_the_old_one(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('pdfs/old.pdf', 'x');
        Storage::disk('local')->put('pdfs/new.pdf', 'x');
        $pdf = PdfDocument::create(['file_path' => 'pdfs/old.pdf']);

        $pdf->update(['file_path' => 'pdfs/new.pdf']);

        Storage::disk('local')->assertMissing('pdfs/old.pdf');
        Storage::disk('local')->assertExists('pdfs/new.pdf');
    }

    public function test_deleting_an_import_removes_the_uploaded_file(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('question-imports/q.pdf', 'x');
        $import = QuestionImport::create(['method' => 'pdf', 'original_name' => 'q.pdf', 'file_path' => 'question-imports/q.pdf', 'status' => 'ready']);

        $import->delete();

        Storage::disk('local')->assertMissing('question-imports/q.pdf');
    }
}
