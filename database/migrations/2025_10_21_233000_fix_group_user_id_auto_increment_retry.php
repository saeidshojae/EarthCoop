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

        $driver = config('database.default');
        if ($driver !== 'mysql') {
            // Only applicable for MySQL/MariaDB
            return;
        }

        $connection = config('database.connections.' . $driver);
        $database = $connection['database'] ?? null;
        if (!$database) return;

        // Check whether `id` is already AUTO_INCREMENT
        $isAutoInc = DB::table('information_schema.COLUMNS')
            ->where('TABLE_SCHEMA', $database)
            ->where('TABLE_NAME', 'group_user')
            ->where('COLUMN_NAME', 'id')
            ->where('EXTRA', 'like', '%auto_increment%')
            ->exists();

        if (!$isAutoInc) {
            try {
                DB::statement('ALTER TABLE `group_user` MODIFY COLUMN `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
            } catch (\Throwable $e) {
                // Try alternative syntax (MariaDB/older MySQL)
                try {
                    DB::statement('ALTER TABLE `group_user` CHANGE `id` `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
                } catch (\Throwable $e2) {
                    // As a last resort, ensure primary key exists on id and then try again
                    try {
                        $hasPk = DB::table('information_schema.TABLE_CONSTRAINTS')
                            ->where('TABLE_SCHEMA', $database)
                            ->where('TABLE_NAME', 'group_user')
                            ->where('CONSTRAINT_TYPE', 'PRIMARY KEY')
                            ->exists();
                        if (!$hasPk) {
                            DB::statement('ALTER TABLE `group_user` ADD PRIMARY KEY (`id`)');
                        }
                        DB::statement('ALTER TABLE `group_user` MODIFY COLUMN `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
                    } catch (\Throwable $e3) {
                        // Intentionally ignore; if all strategies fail, we'll still have the unique index below to keep data consistent
                    }
                }
            }
        }

        // Add a unique index on (group_id, user_id) to prevent duplicates (ignore if it exists)
        try {
            DB::statement('CREATE UNIQUE INDEX `group_user_group_id_user_id_unique` ON `group_user` (`group_id`, `user_id`)');
        } catch (\Throwable $e) {
            // ignore if exists
        }
    }

    public function down(): void
    {
        // No destructive rollback to avoid data loss
    }
};
