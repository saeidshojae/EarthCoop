<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                'ALTER TABLE `notifications` '
                .'ADD COLUMN `sync_sequence` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT AFTER `id`, '
                .'ADD UNIQUE KEY `notifications_sync_sequence_unique` (`sync_sequence`)'
            );

            return;
        }

        Schema::table('notifications', function (Blueprint $table) {
            $table->unsignedBigInteger('sync_sequence', true)
                ->unique('notifications_sync_sequence_unique')
                ->after('id');
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                'ALTER TABLE `notifications` '
                .'DROP INDEX `notifications_sync_sequence_unique`, '
                .'DROP COLUMN `sync_sequence`'
            );

            return;
        }

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropUnique('notifications_sync_sequence_unique');
            $table->dropColumn('sync_sequence');
        });
    }
};
