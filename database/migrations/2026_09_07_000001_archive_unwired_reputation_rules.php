<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ARCHIVED_KEYS = [
        'profile_photo_uploaded',
        'social_links_added',
        'documents_uploaded',
        'bio_added',
        'report_received',
        'bid_canceled',
        'fraud',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('reputation_rules')) {
            return;
        }

        DB::table('reputation_rules')
            ->whereIn('key', self::ARCHIVED_KEYS)
            ->update([
                'active' => false,
                'convertible' => false,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Deliberately irreversible: historical/unwired rules must not be re-enabled
        // automatically by rolling back a schema migration.
    }
};
