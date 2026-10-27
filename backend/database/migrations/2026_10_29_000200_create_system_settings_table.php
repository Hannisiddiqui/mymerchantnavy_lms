<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        // Seed default session limit settings (null defaults defer to config unless configured by Super Admin)
        DB::table('system_settings')->insert([
            [
                'key' => 'student_max_devices',
                'value' => null,
                'description' => 'Maximum concurrent logged-in devices per student (0 = unlimited).',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'admin_session_lifetime_minutes',
                'value' => '120',
                'description' => 'Admin session timeout in minutes.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
