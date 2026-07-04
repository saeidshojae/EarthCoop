<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('chat_requests', 'private_conversation_id')) {
                $column = $table->foreignId('private_conversation_id')
                    ->nullable()
                    ->constrained('private_conversations')
                    ->nullOnDelete();

                if (Schema::hasColumn('chat_requests', 'group_id')) {
                    $column->after('group_id');
                } else {
                    $column->after('status');
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('chat_requests', function (Blueprint $table) {
            if (Schema::hasColumn('chat_requests', 'private_conversation_id')) {
                $table->dropConstrainedForeignId('private_conversation_id');
            }
        });
    }
};
