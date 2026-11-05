<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custom_pages', function (Blueprint $table) {
            $table->string('theme', 20)->default('brand');          // brand | navy | emerald | violet | amber | rose
            $table->string('width', 20)->default('normal');         // narrow | normal | wide | full
            $table->boolean('show_header')->default(true);          // the page title bar above the sections
            $table->text('cover_image_url')->nullable();
            $table->string('access', 20)->default('students');      // public | students | courses
            $table->json('access_course_ids')->nullable();
            $table->timestamp('publish_at')->nullable();
            $table->timestamp('unpublish_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('custom_pages', fn (Blueprint $t) => $t->dropColumn(['theme', 'width', 'show_header', 'cover_image_url', 'access', 'access_course_ids', 'publish_at', 'unpublish_at']));
    }
};
