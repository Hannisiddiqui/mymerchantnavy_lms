<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_pages', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('icon')->nullable()->default('heroicon-o-document-text');
            $table->string('badge_text')->nullable();
            $table->longText('content')->nullable();
            $table->json('blocks')->nullable();
            $table->text('meta_description')->nullable();
            $table->boolean('is_published')->default(true)->index();
            $table->boolean('show_in_sidebar')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_pages');
    }
};

