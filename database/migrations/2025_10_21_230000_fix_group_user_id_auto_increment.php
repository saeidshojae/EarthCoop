<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('group_user')) {
            return;
        }

        if (Schema::hasColumn('group_user', 'id')) {
            // Make the id column AUTO_INCREMENT if it isn't already. If it already is, this is a no-op for MySQL.
            try {
                DB::statement('ALTER TABLE `group_user` MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
            } catch (\Throwable $e) {
                // Ignore if not supported or already auto-increment in this environment.
            }
        }

        // Add a unique index on (group_id, user_id) to prevent duplicates if it doesn't exist.
        try {
            DB::statement('CREATE UNIQUE INDEX `group_user_group_id_user_id_unique` ON `group_user` (`group_id`, `user_id`)');
        } catch (\Throwable $e) {
            // Index may already exist; ignore.
        }
    }

    public function down(): void
    {
        // No-op: we won't revert AUTO_INCREMENT or unique index automatically to avoid risking data loss.
    }
};
