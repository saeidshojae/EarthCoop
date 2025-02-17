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
        Schema::create('settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('district_id')->constrained();
            $table->string('name');
            $table->string('type'); // نوع (شهر یا دهستان)
            $table->string('national_code')->unique(); // کد یکتای شهر یا دهستان
            $table->timestamps(); // اضافه کردن ستون‌های created_at و updated_at
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('settlements');
    }
};