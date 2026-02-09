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
        Schema::create('system_emails', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // نام ایمیل (مثل support, contact, info)
            $table->string('email')->unique(); // آدرس ایمیل کامل
            $table->string('display_name')->nullable(); // نام نمایشی (مثل "پشتیبانی")
            $table->text('description')->nullable(); // توضیحات
            $table->boolean('is_active')->default(true); // فعال/غیرفعال
            $table->boolean('is_default')->default(false); // ایمیل پیش‌فرض
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_emails');
    }
};
