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
        Schema::create('alleys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('street_id')->constrained();
            $table->string('name');
            $table->string('national_code')->unique(); // کد یکتای کوچه
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
        Schema::dropIfExists('alleys');
    }
};