<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('comments')) {
            Schema::create('comments', function (Blueprint $table) {
                $table->id(); // bigint, auto-increment, primary
                $table->integer('blog_id');      // مطابق int(11)
                $table->integer('user_id');      // مطابق int(11)
                $table->string('message', 5000); // varchar(5000)
                $table->integer('parent_id')->nullable(); // int(11) nullable
                $table->timestamps(); // created_at, updated_at

                // (اختیاری) ایندکس برای بهبود عملکرد – در دیتادیکشنری ذکر نشده، اما می‌توانید اضافه کنید
                // $table->index('blog_id');
                // $table->index('user_id');
                // $table->index('parent_id');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('comments');
    }
};