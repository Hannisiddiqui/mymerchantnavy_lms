<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add permissions column to admins table if not already present
        if (! Schema::hasColumn('admins', 'permissions')) {
            Schema::table('admins', function (Blueprint $table) {
                $table->json('permissions')->nullable()->after('role');
            });
        }

        // 2. Create support_queries table for two-way chatbot and WhatsApp management
        if (! Schema::hasTable('support_queries')) {
            Schema::create('support_queries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('student_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('name');
                $table->string('email');
                $table->string('phone')->nullable();
                $table->string('category')->default('General Inquiry');
                $table->string('channel')->default('web_chat'); // web_chat, whatsapp, email
                $table->text('message');
                $table->string('status')->default('open'); // open, in_progress, resolved, closed
                $table->text('admin_reply')->nullable();
                $table->foreignId('replied_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
                $table->timestamp('replied_at')->nullable();
                $table->string('ip_address')->nullable();
                $table->timestamps();
            });
        }

        // 3. Create pending_changes table for Super Admin approval workflow
        if (! Schema::hasTable('pending_changes')) {
            Schema::create('pending_changes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('admin_id')->constrained('admins')->cascadeOnDelete();
                $table->string('admin_name');
                $table->string('admin_email');
                $table->string('action_type')->default('update'); // create, update, delete
                $table->string('model_type'); // Course, Question, Batch, CustomPage
                $table->unsignedBigInteger('model_id')->nullable();
                $table->string('title');
                $table->json('proposed_changes')->nullable();
                $table->string('status')->default('pending'); // pending, approved, rejected
                $table->foreignId('reviewed_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_changes');
        Schema::dropIfExists('support_queries');
        if (Schema::hasColumn('admins', 'permissions')) {
            Schema::table('admins', function (Blueprint $table) {
                $table->dropColumn('permissions');
            });
        }
    }
};
