<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('batches', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('batch_code', 60)->nullable();
            $table->string('target_exam', 100)->nullable()->index();
            $table->text('image_url')->nullable();
            $table->text('description')->nullable();
            $table->json('highlights')->nullable();
            $table->decimal('mrp_price', 10, 2)->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->unsignedInteger('discount_percent')->nullable();
            $table->date('starts_at')->nullable();
            $table->string('validity_text', 120)->nullable();
            $table->date('ends_at')->nullable();
            $table->text('purchase_url')->nullable();
            $table->string('wp_product_id', 191)->nullable();
            $table->string('footer_tag', 191)->nullable();
            $table->boolean('is_live')->default(true)->index();
            $table->string('status', 40)->default('active');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('batch_course', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('batches')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['batch_id', 'course_id']);
        });

        Schema::create('batch_test', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('batches')->cascadeOnDelete();
            $table->foreignId('test_id')->constrained('tests')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['batch_id', 'test_id']);
        });

        Schema::create('batch_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('batches')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('enrolled_at')->useCurrent();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->unique(['batch_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('batch_user');
        Schema::dropIfExists('batch_test');
        Schema::dropIfExists('batch_course');
        Schema::dropIfExists('batches');
    }
};
