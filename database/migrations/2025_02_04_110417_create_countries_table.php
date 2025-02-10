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
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('continent_id')->constrained();
            $table->string('name_en');
            $table->string('name_local')->nullable();
            $table->char('iso2', 2)->unique(); // مثل IR
            $table->char('iso3', 3)->unique(); // مثل IRN
            $table->string('phone_code'); // مثل +98
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('countries');
    }
};
