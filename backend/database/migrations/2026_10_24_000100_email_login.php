<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Students can log in with their EMAIL (a one-time code is emailed). The mobile number stays in the system for the
 * SMS login (LMS_LOGIN_METHOD=phone) but is now optional.
 */
return new class extends Migration
{
    public function up(): void
    {
        // One account per email address: keep the first, clear the email of any later duplicate.
        $seen = [];
        foreach (DB::table('users')->whereNotNull('email')->orderBy('id')->get(['id', 'email']) as $row) {
            $key = mb_strtolower(trim($row->email));
            if (isset($seen[$key])) {
                DB::table('users')->where('id', $row->id)->update(['email' => null]);
            } else {
                $seen[$key] = true;
                DB::table('users')->where('id', $row->id)->update(['email' => $key]);
            }
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 15)->nullable()->change();
            $table->timestamp('email_verified_at')->nullable()->after('email');
            $table->unique('email');
        });

        Schema::table('otp_codes', function (Blueprint $table) {
            $table->string('phone', 15)->nullable()->change();
            $table->string('email')->nullable()->after('phone')->index();
        });
    }

    public function down(): void
    {
        Schema::table('otp_codes', function (Blueprint $table) {
            $table->dropIndex(['email']);
            $table->dropColumn('email');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['email']);
            $table->dropColumn('email_verified_at');
        });
    }
};
