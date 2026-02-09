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
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // نام نقش (مثل: Super Admin)
            $table->string('slug')->unique(); // slug یکتا (مثل: super-admin)
            $table->text('description')->nullable(); // توضیحات نقش
            $table->boolean('is_system')->default(false); // آیا نقش سیستمی است (نمی‌توان حذف کرد)
            $table->integer('order')->default(0); // ترتیب نمایش
            $table->timestamps();
            
            $table->index('slug');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('roles');
    }
};
