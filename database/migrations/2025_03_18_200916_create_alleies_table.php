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
        if (Schema::hasTable('alleies')) {
            return;
        }
        Schema::create('alleies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            if (Schema::hasTable('streets')) {
                $table->foreignId('street_id')->constrained('streets')->onDelete('cascade')->onUpdate('cascade');
            } else {
                $table->unsignedBigInteger('street_id')->nullable();
                $table->index('street_id');
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
        Schema::dropIfExists('alleies');
    }
};
