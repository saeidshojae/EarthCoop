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
        Schema::table('najm_bahar_projects', function (Blueprint $table) {
            // نوع مقصد (User یا Group)
            $table->string('assigned_to_type')->nullable()->comment('User یا Group');
            
            // شناسه مقصد (user_id یا group_id)
            $table->unsignedBigInteger('assigned_to_id')->nullable();
            
            // تاریخ ارجاع
            $table->timestamp('assigned_at')->nullable();
            
            // نظر ارجاع دهنده (مثلاً دلیل ارجاع)
            $table->text('assignment_note')->nullable();
            
            // وضعیت بررسی توسط مقصد (pending, under_review, completed, rejected)
            $table->enum('assignment_status', ['pending', 'under_review', 'completed', 'rejected'])->nullable();
            
            // نظر بررسی کننده
            $table->text('assignment_review_note')->nullable();
            
            // تاریخ تکمیل بررسی توسط مقصد
            $table->timestamp('assignment_completed_at')->nullable();
            
            // ایندکس‌ها
            $table->index(['assigned_to_type', 'assigned_to_id']);
            $table->index('assignment_status');
            $table->index('assigned_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('najm_bahar_projects', function (Blueprint $table) {
            $table->dropIndex(['assigned_to_type', 'assigned_to_id']);
            $table->dropIndex(['assignment_status']);
            $table->dropIndex(['assigned_at']);
            
            $table->dropColumn([
                'assigned_to_type',
                'assigned_to_id',
                'assigned_at',
                'assignment_note',
                'assignment_status',
                'assignment_review_note',
                'assignment_completed_at',
            ]);
        });
    }
};
