<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('najm_bahar_agreements', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('content');
            $table->foreignId('parent_id')->nullable()->constrained('najm_bahar_agreements')->onDelete('cascade');
            $table->integer('order')->default(0)->index();
            $table->timestamps();
            
            $table->index('parent_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('najm_bahar_agreements');
    }
};
