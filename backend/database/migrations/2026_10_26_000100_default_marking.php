<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** New tests default to +2 for a correct answer and -0.25 for a wrong one. Existing tests are not changed. */
    public function up(): void
    {
        Schema::table('tests', function (Blueprint $table) {
            $table->decimal('marks_correct', 5, 2)->default(2)->change();
            $table->decimal('marks_wrong', 5, 2)->default(0.25)->change();
        });
    }

    public function down(): void
    {
        Schema::table('tests', function (Blueprint $table) {
            $table->decimal('marks_correct', 5, 2)->default(4)->change();
            $table->decimal('marks_wrong', 5, 2)->default(1)->change();
        });
    }
};
