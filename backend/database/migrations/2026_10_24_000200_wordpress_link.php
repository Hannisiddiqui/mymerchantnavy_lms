<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The id(s) the WordPress shop uses for this course (comma separated when several products give the same course).
        Schema::table('courses', function (Blueprint $table) {
            $table->string('wordpress_product_id', 191)->nullable()->after('slug');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('wordpress_user_id')->nullable()->index()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('courses', fn (Blueprint $table) => $table->dropColumn('wordpress_product_id'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('wordpress_user_id'));
    }
};
