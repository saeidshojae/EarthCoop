<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_feed_sequences', function (Blueprint $table) {
            $table->foreignId('group_id')->primary()->constrained('groups')->cascadeOnDelete();
            $table->unsignedBigInteger('last_sequence')->default(0);
            $table->timestamps();
        });

        Schema::create('group_feed_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->unsignedBigInteger('sequence');
            $table->string('type', 30);
            $table->unsignedBigInteger('content_id');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->unique(['group_id', 'sequence'], 'group_feed_group_sequence_unique');
            $table->unique(['type', 'content_id'], 'group_feed_type_content_unique');
            $table->index(['group_id', 'type', 'sequence'], 'group_feed_group_type_sequence_index');
            $table->index(['actor_id', 'group_id', 'sequence'], 'group_feed_actor_group_sequence_index');
        });

        Schema::table('group_user', function (Blueprint $table) {
            $table->unsignedBigInteger('last_read_feed_sequence')->default(0)->after('last_read_message_id');
            $table->index(['group_id', 'user_id', 'last_read_feed_sequence'], 'group_user_feed_cursor_index');
        });
    }

    public function down(): void
    {
        Schema::table('group_user', function (Blueprint $table) {
            $table->dropIndex('group_user_feed_cursor_index');
            $table->dropColumn('last_read_feed_sequence');
        });
        Schema::dropIfExists('group_feed_items');
        Schema::dropIfExists('group_feed_sequences');
    }
};
