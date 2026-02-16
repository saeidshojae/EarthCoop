<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('najm_bahar_project_reviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('reviewer_id')->nullable(); // کاربر ادمین که بررسی کرده
            
            $table->enum('action', [
                'submitted',        // ارسال شده
                'under_review',     // شروع بررسی
                'approved',         // تایید
                'rejected',         // رد
                'revision_requested', // درخواست اصلاح
                'resubmitted',      // ارسال مجدد
                'archived'          // بایگانی
            ]);
            
            $table->text('comment')->nullable(); // نظر/توضیحات
            $table->json('metadata')->nullable(); // اطلاعات اضافی
            
            $table->timestamps();

            // ایندکس‌ها
            $table->foreign('project_id')->references('id')->on('najm_bahar_projects')->onDelete('cascade');
            $table->foreign('reviewer_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['project_id', 'created_at']);
            $table->index('action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('najm_bahar_project_reviews');
    }
};
