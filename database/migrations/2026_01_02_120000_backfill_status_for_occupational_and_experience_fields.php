<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backfill status=1 for pre-existing records that were created before
     * the status columns were introduced (2025_11_26_022811... migration).
     *
     * This prevents all legacy specialties/experiences from showing as "pending"
     * in UI, while keeping newly created items (after that date) pending by default.
     */
    public function up(): void
    {
        // Timestamp inferred from migration filename: 2025_11_26_022811
        // Using a plain string keeps it DB-agnostic enough for MySQL/Postgres.
        $cutoff = '2025-11-26 02:28:11';

        if (Schema::hasTable('occupational_fields') && Schema::hasColumn('occupational_fields', 'status')) {
            DB::table('occupational_fields')
                ->where('status', 0)
                ->whereNotNull('created_at')
                ->where('created_at', '<', $cutoff)
                ->update(['status' => 1]);
        }

        if (Schema::hasTable('experience_fields') && Schema::hasColumn('experience_fields', 'status')) {
            DB::table('experience_fields')
                ->where('status', 0)
                ->whereNotNull('created_at')
                ->where('created_at', '<', $cutoff)
                ->update(['status' => 1]);
        }
    }

    public function down(): void
    {
        // Intentionally no-op:
        // Reverting approvals is potentially destructive and not reliably reversible.
    }
};


