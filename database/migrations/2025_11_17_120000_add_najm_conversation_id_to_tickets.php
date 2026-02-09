<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('tickets')) {
            Schema::table('tickets', function (Blueprint $table) {
                if (! Schema::hasColumn('tickets', 'najm_conversation_id')) {
                    $table->string('najm_conversation_id')->nullable()->unique()->after('tracking_code');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tickets')) {
            Schema::table('tickets', function (Blueprint $table) {
                if (Schema::hasColumn('tickets', 'najm_conversation_id')) {
                    $table->dropUnique(['najm_conversation_id']);
                    $table->dropColumn('najm_conversation_id');
                }
            });
        }
    }
};
