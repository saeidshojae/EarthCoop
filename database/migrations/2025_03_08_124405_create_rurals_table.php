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
        Schema::create('rurals', function (Blueprint $table) {
            $table->id();

            $table->string('name');

            $table->unsignedBigInteger('province_id')->nullable();
            $table->unsignedBigInteger('county_id')->nullable();
            $table->unsignedBigInteger('district_id')->nullable();

            $table->string('amar_code')->nullable();

            $table->tinyInteger('status')->default(1);

            $table->timestamps();

            $table->foreign('province_id')
                ->references('id')
                ->on('provinces')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreign('county_id')
                ->references('id')
                ->on('counties')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreign('district_id')
                ->references('id')
                ->on('districts')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->index('province_id');
            $table->index('county_id');
            $table->index('district_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rurals');
    }
};