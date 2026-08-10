<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_chat_outbox', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_id')->unique();
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->foreignId('feed_item_id')->nullable()->constrained('group_feed_items')->cascadeOnDelete();
            $table->unsignedBigInteger('sequence');
            $table->string('type', 60);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('payload');
            $table->string('status', 20)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('available_at')->index();
            $table->timestamp('published_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->index(['status', 'available_at', 'id'], 'group_chat_outbox_dispatch_index');
            $table->index(['group_id', 'sequence', 'id'], 'group_chat_outbox_group_sequence_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_chat_outbox');
    }
};
