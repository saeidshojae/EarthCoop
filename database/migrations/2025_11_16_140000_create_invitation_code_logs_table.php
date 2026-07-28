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
        Schema::create('invitation_code_logs', function (Blueprint $table) {
            $table->id();

            // Merged from:
            // 2025_11_17_014749_make_invitation_code_id_nullable_in_invitation_code_logs_table.php
            $table->unsignedBigInteger('invitation_code_id')->nullable();

            $table->string('action', 32);

            $table->unsignedBigInteger('actor_id')->nullable();

            $table->longText('meta')->nullable();

            $table->timestamps();

            $table->index('invitation_code_id');
            $table->index('action');
            $table->index('actor_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invitation_code_logs');
    }
};