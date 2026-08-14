<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_sync_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->string('event_type', 80);
            $table->string('action', 40);
            $table->string('content_type', 30)->nullable();
            $table->unsignedBigInteger('content_id')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('payload');
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['group_id', 'id'], 'group_sync_group_cursor_index');
            $table->index(['group_id', 'content_type', 'content_id'], 'group_sync_content_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_sync_events');
    }
};
