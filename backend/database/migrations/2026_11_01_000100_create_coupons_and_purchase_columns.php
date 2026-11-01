<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('description')->nullable();
            $table->string('type', 16)->default('percent');      // percent | fixed
            $table->decimal('value', 10, 2);                      // percent (1-100) or rupees off
            $table->decimal('max_discount', 10, 2)->nullable();   // cap for percent coupons
            $table->decimal('min_amount', 10, 2)->nullable();     // smallest course price the coupon works on
            $table->json('product_ids')->nullable();              // WordPress product ids it works on; empty = every product
            $table->unsignedInteger('usage_limit')->nullable();   // total uses; empty = unlimited
            $table->unsignedInteger('per_student_limit')->default(1);
            $table->unsignedInteger('used_count')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('enrollments', function (Blueprint $table) {
            $table->string('coupon_code', 40)->nullable()->after('amount');
            $table->decimal('discount_amount', 10, 2)->nullable()->after('coupon_code');
            $table->timestamp('purchase_email_sent_at')->nullable()->after('discount_amount');
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', fn (Blueprint $t) => $t->dropColumn(['coupon_code', 'discount_amount', 'purchase_email_sent_at']));
        Schema::dropIfExists('coupons');
    }
};
