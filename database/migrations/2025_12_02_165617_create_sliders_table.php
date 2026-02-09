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
        Schema::create('sliders', function (Blueprint $table) {
            $table->id();
            $table->string('src'); // مسیر تصویر
            $table->string('alt')->nullable(); // متن جایگزین
            $table->string('link')->nullable(); // لینک (اختیاری)
            $table->tinyInteger('position')->default(1); // 1 = صفحه خانه، 2 = صفحه خوش‌آمد
            $table->timestamps();
            
            $table->index('position');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('sliders');
    }
};
