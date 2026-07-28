<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('chat_requests', 'message')) {
                $table->text('message')->nullable()->after('status');
            }

            if (!Schema::hasColumn('chat_requests', 'request_to_group')) {
                $table->unsignedBigInteger('request_to_group')->nullable()->after('message');
            }
        });
    }

    public function down(): void
    {
        Schema::table('chat_requests', function (Blueprint $table) {
            if (Schema::hasColumn('chat_requests', 'request_to_group')) {
                $table->dropColumn('request_to_group');
            }

            if (Schema::hasColumn('chat_requests', 'message')) {
                $table->dropColumn('message');
            }
        });
    }
};
