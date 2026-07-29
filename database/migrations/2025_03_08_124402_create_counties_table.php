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
        if (Schema::hasTable('counties')) {
            return;
        }

        Schema::create('counties', function (Blueprint $table) {
            $table->id();

            $table->string('name');

            $table->foreignId('province_id')
                ->nullable()
                ->constrained('provinces');

            $table->string('amar_code', 50)->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('counties');
    }
};