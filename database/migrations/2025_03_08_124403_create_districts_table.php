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
        if (Schema::hasTable('districts')) {
            return;
        }

        Schema::create('districts', function (Blueprint $table) {
            $table->id();

            $table->string('name');

            $table->foreignId('province_id')
                ->nullable()
                ->constrained('provinces');

            $table->foreignId('county_id')
                ->nullable()
                ->constrained('counties');

            $table->string('amar_code', 50)->nullable();

            $table->tinyInteger('status')->default(1);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('districts');
    }
};