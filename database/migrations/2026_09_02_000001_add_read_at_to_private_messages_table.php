<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('private_messages', function (Blueprint $table) {
            $table->timestamp('read_at')->nullable()->after('message');
            $table->index(
                ['private_conversation_id', 'read_at'],
                'private_messages_conversation_read_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('private_messages', function (Blueprint $table) {
            $table->dropIndex('private_messages_conversation_read_idx');
            $table->dropColumn('read_at');
        });
    }
};
