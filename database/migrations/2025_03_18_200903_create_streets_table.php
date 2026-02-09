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
        if (Schema::hasTable('streets')) {
            return;
        }
        Schema::create('streets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            if (Schema::hasTable('neighborhoods')) {
                $table->foreignId('neighborhood_id')->constrained('neighborhoods')->onDelete('cascade')->onUpdate('cascade');
            } else {
                $table->unsignedBigInteger('neighborhood_id')->nullable();
                $table->index('neighborhood_id');
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
        Schema::dropIfExists('streets');
    }
};
