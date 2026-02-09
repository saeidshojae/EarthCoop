<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['message', 'post', 'poll', 'user'])->index();
            $table->foreignId('reported_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('reported_item_id')->nullable(); // ID مورد گزارش شده (message_id, blog_id, poll_id, user_id)
            $table->string('reason')->nullable(); // دلیل اصلی گزارش
            $table->text('description')->nullable(); // توضیحات تکمیلی
            $table->enum('status', ['pending', 'reviewed', 'resolved', 'rejected', 'archived'])->default('pending')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('admin_note')->nullable(); // یادداشت ادمین
            $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium')->index();
            $table->foreignId('group_id')->nullable()->constrained('groups')->onDelete('cascade');
            $table->integer('report_count')->default(1); // تعداد گزارشات برای همان آیتم
            $table->json('metadata')->nullable(); // اطلاعات اضافی (مثل محتوای مورد گزارش)
            $table->timestamps();
            
            $table->index(['type', 'reported_item_id']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('reports');
    }
};
