<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('cities')) {
            return;
        }

        Schema::create('cities', function (Blueprint $table) {
            $table->id();

            $table->string('name')->nullable();

            $table->tinyInteger('city_type')->nullable();

            $table->unsignedBigInteger('province_id')->nullable();
            $table->unsignedBigInteger('county_id')->nullable();
            $table->unsignedBigInteger('district_id')->nullable();

            $table->string('amar_code')->nullable();

            $table->tinyInteger('status')->default(1);

            $table->timestamps();

            $table->foreign('province_id')
                ->references('id')
                ->on('provinces');

            $table->foreign('county_id')
                ->references('id')
                ->on('counties');

            $table->foreign('district_id')
                ->references('id')
                ->on('districts');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cities');
    }
};