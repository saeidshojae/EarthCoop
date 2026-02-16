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
        Schema::create('najm_bahar_project_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // نام دسته‌بندی
            $table->unsignedBigInteger('parent_id')->nullable(); // والد برای ساختار سه سطحی
            $table->integer('level')->default(1); // سطح: 1=صنعت، 2=زیرصنعت، 3=نوع پروژه
            $table->integer('order')->default(0); // ترتیب نمایش
            $table->boolean('status')->default(true); // فعال/غیرفعال
            $table->text('description')->nullable(); // توضیحات
            $table->timestamps();

            // ایندکس‌ها
            $table->foreign('parent_id')->references('id')->on('najm_bahar_project_categories')->onDelete('cascade');
            $table->index(['parent_id', 'level']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('najm_bahar_project_categories');
    }
};
