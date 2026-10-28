<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->string('validity_type', 32)->default('6_months')->after('is_active');
            $table->unsignedInteger('validity_duration_months')->nullable()->default(6)->after('validity_type');
            $table->unsignedInteger('validity_duration_days')->nullable()->after('validity_duration_months');
            $table->date('validity_fixed_expiry_date')->nullable()->after('validity_duration_days');
        });

        Schema::table('enrollments', function (Blueprint $table) {
            $table->timestamp('purchase_date')->nullable()->after('assigned_at');
            $table->timestamp('expires_at')->nullable()->index()->after('purchase_date');
            $table->string('purchase_type', 32)->default('assigned')->index()->after('expires_at'); // 'paid', 'free', 'assigned'
            $table->string('order_id', 191)->nullable()->index()->after('purchase_type');
            $table->string('payment_id', 191)->nullable()->after('order_id');
            $table->decimal('amount', 10, 2)->nullable()->after('payment_id');
            $table->string('status', 32)->default('active')->index()->after('amount'); // 'active', 'expired', 'cancelled'
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn([
                'validity_type',
                'validity_duration_months',
                'validity_duration_days',
                'validity_fixed_expiry_date',
            ]);
        });

        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropColumn([
                'purchase_date',
                'expires_at',
                'purchase_type',
                'order_id',
                'payment_id',
                'amount',
                'status',
            ]);
        });
    }
};

