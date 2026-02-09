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
        Schema::create('najm_bahar_fees', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['fixed', 'percentage', 'combined'])->default('fixed');
            $table->integer('fixed_amount')->default(0);
            $table->decimal('percentage', 5, 2)->nullable()->default(0);
            $table->enum('transaction_type', ['all', 'immediate', 'scheduled', 'fee', 'adjustment'])->default('all');
            $table->integer('min_amount')->nullable();
            $table->integer('max_amount')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
            
            $table->index('is_active');
            $table->index('transaction_type');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('najm_bahar_fees');
    }
};
