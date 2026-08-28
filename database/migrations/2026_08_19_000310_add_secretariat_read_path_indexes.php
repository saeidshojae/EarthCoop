<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('secretariat_records', function (Blueprint $table) {
            // Supports office work-queue reads:
            // WHERE office_id = ? AND status = ? ORDER BY updated_at, id
            $table->index(
                ['office_id', 'status', 'updated_at', 'id'],
                'secretariat_records_office_status_updated_idx'
            );

            // Supports deterministic office-scoped registry/search reads:
            // WHERE office_id = ? ORDER BY registered_at DESC, id DESC
            $table->index(
                ['office_id', 'registered_at', 'id'],
                'secretariat_records_office_registered_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('secretariat_records', function (Blueprint $table) {
            $table->dropIndex('secretariat_records_office_status_updated_idx');
            $table->dropIndex('secretariat_records_office_registered_idx');
        });
    }
};
