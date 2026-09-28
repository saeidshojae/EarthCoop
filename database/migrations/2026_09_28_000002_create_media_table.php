<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('uploader_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('purpose', 80);
            $table->string('visibility', 20)->default('private');
            $table->string('status', 20)->default('ready');
            $table->string('disk', 50)->default('local');
            $table->string('storage_key');
            $table->string('original_name')->nullable();
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size');
            $table->string('sha256', 64);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('privacy_status', 30)->default('pending');
            $table->string('scan_status', 30)->default('pending');
            $table->timestamps();
            $table->index(['uploader_user_id', 'purpose']);
            $table->index(['sha256', 'size']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
