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
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // نام دسترسی (مثل: مشاهده کاربران)
            $table->string('slug')->unique(); // slug یکتا (مثل: users.view)
            $table->text('description')->nullable(); // توضیحات دسترسی
            $table->string('module')->nullable(); // ماژول (مثل: users, groups, blog)
            $table->integer('order')->default(0); // ترتیب نمایش
            $table->timestamps();
            
            $table->index('slug');
            $table->index('module');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('permissions');
    }
};
