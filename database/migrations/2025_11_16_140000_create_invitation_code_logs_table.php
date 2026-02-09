<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('invitation_code_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('invitation_code_id')->index();
            $table->string('action', 32)->index(); // create, generate, delete, invalidate, use, etc.
            $table->unsignedBigInteger('actor_id')->nullable()->index();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('invitation_code_id')->references('id')->on('invitation_codes')->onDelete('cascade');
            $table->foreign('actor_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitation_code_logs');
    }
};


