<?php

use App\Enums\AdminRole;
use App\Models\Admin;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->string('role')->default(AdminRole::Admin->value)->after('email');
        });

        // Ensure the dedicated super admin account email has the super_admin role if it exists
        DB::table('admins')
            ->where('email', Admin::SUPER_ADMIN_EMAIL)
            ->update(['role' => AdminRole::SuperAdmin->value]);
    }

    public function down(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
