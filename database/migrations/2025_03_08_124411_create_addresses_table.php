<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();

            $table->integer('user_id');

            $table->integer('continent_id');
            $table->integer('country_id');
            $table->integer('province_id');
            $table->integer('county_id');
            $table->integer('section_id');

            $table->integer('city_id')->nullable();
            $table->integer('rural_id')->nullable();
            $table->integer('village_id')->nullable();
            $table->integer('region_id')->nullable();

            $table->integer('neighborhood_id');

            $table->integer('street_id')->nullable();
            $table->integer('alley_id')->nullable();

            $table->integer('status')->default(1);

            $table->dateTime('created_at');
            $table->dateTime('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};