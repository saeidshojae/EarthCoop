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
        Schema::create('invitation_codes', function (Blueprint $table) {
            $table->id();

            $table->string('code')->unique();

            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('used_by')->nullable();

            $table->boolean('used')->default(false);

            // Merged from:
            // 2025_11_16_154500_add_used_at_to_invitation_codes_table.php
            $table->timestamp('used_at')->nullable();

            $table->timestamp('expire_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invitation_codes');
    }
};