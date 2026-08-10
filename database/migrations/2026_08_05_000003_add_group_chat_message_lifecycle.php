<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->string('lifecycle_state', 20)->default('sent')->after('edited');
            $table->timestamp('edited_at')->nullable()->after('edited_by');
            $table->timestamp('delivered_at')->nullable()->after('edited_at');
            $table->timestamp('deleted_at')->nullable()->after('delivered_at');
            $table->foreignId('deleted_by')->nullable()->after('deleted_at')->constrained('users')->nullOnDelete();
            $table->index(['group_id', 'lifecycle_state', 'id'], 'messages_group_lifecycle_id_index');
        });

        Schema::create('group_chat_content_edits', function (Blueprint $table) {
            $table->id();
            $table->string('content_type', 30);
            $table->unsignedBigInteger('content_id');
            $table->foreignId('edited_by')->constrained('users')->cascadeOnDelete();
            $table->longText('old_content')->nullable();
            $table->longText('new_content')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['content_type', 'content_id', 'id'], 'group_chat_edits_content_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_chat_content_edits');
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex('messages_group_lifecycle_id_index');
            $table->dropForeign(['deleted_by']);
            $table->dropColumn(['lifecycle_state', 'edited_at', 'delivered_at', 'deleted_at', 'deleted_by']);
        });
    }
};
