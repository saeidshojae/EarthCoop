<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * اجرای Migration ها
     */
    public function up(): void
    {
        // جدول مکالمات
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('title')->nullable();
            $table->string('agent_type')->default('auto'); // auto, engineer, pilot, steward, guide
            $table->enum('status', ['active', 'archived', 'deleted'])->default('active');
            $table->timestamps();
            
            $table->index('user_id');
            $table->index('status');
            $table->index('created_at');
        });

        // جدول پیام‌های مکالمه
        Schema::create('conversation_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->onDelete('cascade');
            $table->enum('role', ['user', 'assistant', 'system']);
            $table->text('content');
            $table->json('metadata')->nullable(); // برای اطلاعات اضافی
            $table->timestamps();
            
            $table->index('conversation_id');
            $table->index('role');
        });

        // جدول تعاملات AI (برای لاگ و آمار)
        Schema::create('ai_interactions', function (Blueprint $table) {
            $table->id();
            $table->string('agent_role'); // engineer, pilot, steward, guide
            $table->text('input');
            $table->text('output');
            $table->string('model')->default('gpt-4-turbo-preview');
            $table->integer('tokens_used')->default(0);
            $table->decimal('cost', 10, 4)->default(0);
            $table->integer('response_time_ms')->nullable(); // زمان پاسخ (میلی‌ثانیه)
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->timestamps();
            
            $table->index('agent_role');
            $table->index('created_at');
            $table->index('user_id');
        });

        // جدول بازخوردها
        Schema::create('feedbacks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['bug', 'feature_request', 'improvement', 'complaint', 'praise', 'other']);
            $table->string('subject');
            $table->text('content');
            $table->integer('rating')->nullable(); // 1-5
            $table->boolean('analyzed')->default(false);
            $table->text('ai_analysis')->nullable();
            $table->enum('status', ['new', 'in_review', 'planned', 'implemented', 'rejected', 'closed'])->default('new');
            $table->enum('priority', ['low', 'medium', 'high', 'critical'])->nullable();
            $table->timestamps();
            
            $table->index('type');
            $table->index('status');
            $table->index('analyzed');
            $table->index('priority');
        });

        // جدول نقشه‌راه‌ها
        Schema::create('roadmaps', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->longText('content'); // محتوای نقشه راه (Markdown)
            $table->string('timeframe'); // 3 months, 6 months, 1 year
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->enum('status', ['draft', 'active', 'completed', 'archived'])->default('draft');
            $table->integer('progress_percentage')->default(0);
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
            
            $table->index('status');
            $table->index('created_by');
        });

        // جدول اسپرینت‌ها
        Schema::create('sprints', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('goals')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['planning', 'active', 'completed', 'cancelled'])->default('planning');
            $table->integer('progress_percentage')->default(0);
            $table->integer('planned_hours')->default(0);
            $table->integer('actual_hours')->default(0);
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
            
            $table->index('status');
            $table->index('start_date');
            $table->index('end_date');
        });

        // جدول کارهای اسپرینت
        Schema::create('sprint_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sprint_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('type', ['feature', 'bug', 'improvement', 'documentation', 'other'])->default('feature');
            $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->integer('estimated_hours')->default(0);
            $table->integer('actual_hours')->default(0);
            $table->foreignId('assigned_to')->nullable()->constrained('users')->onDelete('set null');
            $table->enum('status', ['todo', 'in_progress', 'review', 'testing', 'done', 'blocked'])->default('todo');
            $table->text('blockers')->nullable(); // موانع
            $table->timestamps();
            
            $table->index('sprint_id');
            $table->index('status');
            $table->index('assigned_to');
            $table->index('priority');
        });

        // جدول گزارش‌های سلامت سیستم
        Schema::create('system_health_reports', function (Blueprint $table) {
            $table->id();
            $table->json('metrics'); // معیارهای سیستم
            $table->text('ai_analysis'); // تحلیل AI
            $table->enum('overall_status', ['healthy', 'warning', 'critical'])->default('healthy');
            $table->json('recommendations')->nullable(); // پیشنهادات
            $table->json('alerts')->nullable(); // هشدارها
            $table->timestamps();
            
            $table->index('overall_status');
            $table->index('created_at');
        });

        // جدول تولید کد توسط AI
        Schema::create('ai_code_generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->text('specification'); // درخواست
            $table->string('type'); // model, controller, migration, etc.
            $table->longText('generated_code');
            $table->enum('status', ['pending_review', 'approved', 'rejected', 'implemented'])->default('pending_review');
            $table->text('review_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            
            $table->index('status');
            $table->index('type');
            $table->index('user_id');
        });
    }

    /**
     * بازگشت Migration ها
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_code_generations');
        Schema::dropIfExists('system_health_reports');
        Schema::dropIfExists('sprint_tasks');
        Schema::dropIfExists('sprints');
        Schema::dropIfExists('roadmaps');
        Schema::dropIfExists('feedbacks');
        Schema::dropIfExists('ai_interactions');
        Schema::dropIfExists('conversation_messages');
        Schema::dropIfExists('conversations');
    }
};
