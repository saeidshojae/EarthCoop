<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('group_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('ended_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 160);
            $table->text('subject')->nullable();
            $table->text('agenda')->nullable();
            $table->enum('status', ['scheduled', 'active', 'ended'])->default('scheduled');
            $table->timestamp('starts_at');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'starts_at']);
            $table->index(['group_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_sessions');
    }
};
