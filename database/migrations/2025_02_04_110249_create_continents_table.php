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
        Schema::create('continents', function (Blueprint $table) {
            $table->id();
            $table->string('name_en')->unique(); // نام انگلیسی
            $table->string('name_local')->nullable(); // نام محلی
            $table->string('code', 3)->unique(); // کد سه حرفی (مثلاً EUR برای اروپا)
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('continents');
    }
};
