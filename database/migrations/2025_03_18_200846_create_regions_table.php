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
        if (Schema::hasTable('regions')) {
            return;
        }
        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            if (Schema::hasTable('cities')) {
                $table->foreignId('city_id')->constrained('cities')->onDelete('cascade')->onUpdate('cascade');
            } else {
                // Create as a plain column if cities table is not present to avoid FK issues
                $table->unsignedBigInteger('city_id')->nullable();
                $table->index('city_id');
            }
            $table->tinyInteger('status')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('regions');
    }
};
