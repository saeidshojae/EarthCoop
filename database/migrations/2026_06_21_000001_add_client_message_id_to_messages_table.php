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
        if (! Schema::hasTable('messages')) {
            return;
        }

        Schema::table('messages', function (Blueprint $table) {
            if (! Schema::hasColumn('messages', 'client_message_id')) {
                $table->string('client_message_id', 100)->nullable()->after('voice_message');
                $table->unique(['group_id', 'user_id', 'client_message_id'], 'messages_group_user_client_message_id_unique');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('messages')) {
            return;
        }

        Schema::table('messages', function (Blueprint $table) {
            if (Schema::hasColumn('messages', 'client_message_id')) {
                $table->dropUnique('messages_group_user_client_message_id_unique');
                $table->dropColumn('client_message_id');
            }
        });
    }
};
