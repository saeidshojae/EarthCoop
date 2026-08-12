<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_requests', function (Blueprint $table) {
            $table->foreignId('request_to_group')->nullable()->after('receiver_id')
                ->constrained('groups')->nullOnDelete()->index();
        });
    }

    public function down(): void
    {
        Schema::table('chat_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('request_to_group');
        });
    }
};
