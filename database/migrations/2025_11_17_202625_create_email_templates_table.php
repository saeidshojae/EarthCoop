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
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // نام قالب
            $table->string('subject'); // موضوع ایمیل
            $table->text('body'); // محتوای ایمیل (HTML)
            $table->text('variables')->nullable(); // متغیرهای قابل استفاده (JSON)
            $table->string('category')->nullable(); // دسته‌بندی (invitation, notification, etc.)
            $table->boolean('is_active')->default(true); // فعال/غیرفعال
            $table->text('description')->nullable(); // توضیحات
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('email_templates');
    }
};
