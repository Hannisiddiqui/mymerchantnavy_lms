<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Preview students are the accounts admins use to look at the student dashboard. They are kept out of every count and report.
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_preview')->default(false)->index();
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('is_preview'));
    }
};
