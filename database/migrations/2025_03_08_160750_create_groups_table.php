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
        if (Schema::hasTable('groups')) {
            return;
        }

        Schema::create('groups', function (Blueprint $table) {
            $table->id();

            $table->string('group_type')->default('0');
            $table->string('name');
            $table->string('category')->nullable();

            $table->unsignedBigInteger('location_id')->nullable();

            $table->unsignedBigInteger('specialty_id')->nullable();
            $table->unsignedBigInteger('experience_id')->nullable();
            $table->unsignedBigInteger('age_group_id')->nullable();

            $table->string('location_level')->nullable();
            $table->unsignedBigInteger('address_id')->nullable();

            $table->boolean('is_open')->default(true);

            $table->string('gender')->nullable();
            $table->string('age_group_title')->nullable();

            $table->text('description')->nullable();
            $table->string('avatar')->nullable();

            $table->timestamp('last_activity_at')->nullable();

            $table->timestamps();

            $table->foreign('specialty_id')
                ->references('id')
                ->on('occupational_fields')
                ->nullOnDelete();

            $table->foreign('experience_id')
                ->references('id')
                ->on('experience_fields')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('groups');
    }
};