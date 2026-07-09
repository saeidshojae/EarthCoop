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
        if (Schema::hasTable('najm_bahar_projects')) {
            return;
        }

        Schema::create('najm_bahar_projects', function (Blueprint $table) {
            $table->id();
            
            // ارتباط با کاربر یا گروه (polymorphic)
            $table->morphs('owner'); // owner_id, owner_type (User یا Group)
            
            // ارتباط با دسته‌بندی
            $table->unsignedBigInteger('category_level1_id')->nullable(); // صنعت
            $table->unsignedBigInteger('category_level2_id')->nullable(); // زیرصنعت
            $table->unsignedBigInteger('category_level3_id')->nullable(); // نوع پروژه
            
            // اطلاعات پروژه
            $table->string('title'); // عنوان پروژه
            $table->enum('project_type', ['public', 'private'])->default('public'); // عمومی/خصوصی
            $table->text('summary'); // خلاصه طرح
            $table->longText('description')->nullable(); // توضیحات کامل
            
            // اطلاعات مالی
            $table->bigInteger('required_capital'); // مبلغ سرمایه مورد نیاز (به گل)
            $table->decimal('profit_percentage', 5, 2)->nullable(); // درصد سود پیشنهادی
            $table->integer('investment_duration_months')->nullable(); // مدت سرمایه‌گذاری به ماه
            
            // فایل‌های پیوست
            $table->json('attachments')->nullable(); // فایل‌های پیوست (آرایه)
            
            // وضعیت پروژه
            $table->enum('status', [
                'draft',           // پیش‌نویس
                'pending',         // در انتظار بررسی
                'under_review',    // در حال بررسی
                'approved',        // تایید شده
                'rejected',        // رد شده
                'archived'         // بایگانی شده
            ])->default('draft');
            
            // یادداشت‌های ادمین
            $table->text('admin_notes')->nullable(); // یادداشت‌های خصوصی ادمین
            $table->text('rejection_reason')->nullable(); // دلیل رد (نمایش به کاربر)
            
            // تاریخ‌های مهم
            $table->timestamp('submitted_at')->nullable(); // تاریخ ارسال
            $table->timestamp('reviewed_at')->nullable(); // تاریخ بررسی
            $table->timestamp('approved_at')->nullable(); // تاریخ تایید
            $table->timestamp('archived_at')->nullable(); // تاریخ بایگانی
            
            $table->timestamps();
            $table->softDeletes(); // حذف نرم

            // ایندکس‌ها
            $table->index('status');
            $table->index('project_type');
            $table->index(['category_level1_id', 'category_level2_id', 'category_level3_id'], 'category_index');
            $table->index('submitted_at');
            $table->index('approved_at');
            
            // کلیدهای خارجی
            $table->foreign('category_level1_id')->references('id')->on('najm_bahar_project_categories')->onDelete('set null');
            $table->foreign('category_level2_id')->references('id')->on('najm_bahar_project_categories')->onDelete('set null');
            $table->foreign('category_level3_id')->references('id')->on('najm_bahar_project_categories')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('najm_bahar_projects');
    }
};
