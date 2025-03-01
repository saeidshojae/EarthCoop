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
        Schema::create('locations', function (Blueprint $table) {
            $table->id(); // کلید اصلی
            $table->string('name'); // نام اصلی
            $table->string('name_en')->nullable(); // نام انگلیسی (اختیاری)
            $table->string('name_local')->nullable(); // نام محلی (اختیاری)
            $table->string('code', 10)->nullable(); // کد (اختیاری)
            $table->string('national_code')->nullable(); // کد ملی (اختیاری)
            $table->foreignId('parent_id')->nullable()->constrained('locations')->onDelete('cascade'); // کلید خارجی برای سلسله‌مراتب
            $table->timestamps(); // تاریخ ایجاد و به‌روزرسانی
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('locations');
    }
};
