<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A student's own notes on a piece of learning content (a PDF, a written note, a video). For a PDF a note can be
        // pinned to the page it was written on. Private: only that student ever sees them.
        Schema::create('student_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('chapter_content_id')->constrained('chapter_contents')->cascadeOnDelete();
            $table->unsignedSmallInteger('page')->nullable();
            $table->text('body');
            $table->timestamps();

            $table->index(['user_id', 'chapter_content_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_notes');
    }
};
