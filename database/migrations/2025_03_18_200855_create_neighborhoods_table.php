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
        if (Schema::hasTable('neighborhoods')) {
            return;
        }
        Schema::create('neighborhoods', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            if (Schema::hasTable('regions')) {
                $table->foreignId('region_id')->constrained('regions')->onDelete('cascade')->onUpdate('cascade');
            } else {
                $table->unsignedBigInteger('region_id')->nullable();
                $table->index('region_id');
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
        Schema::dropIfExists('neighborhoods');
    }
};
