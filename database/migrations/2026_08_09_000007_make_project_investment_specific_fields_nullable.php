<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // These fields belong only to the auction-shares funding method.
        // Capital-participation projects intentionally store NULL here.
        DB::statement('ALTER TABLE `najm_bahar_projects` MODIFY `total_shares` INT UNSIGNED NULL');
        DB::statement('ALTER TABLE `najm_bahar_projects` MODIFY `initial_auction_percent` DECIMAL(5,2) NULL');
    }

    public function down(): void
    {
        // Historical behavior used defaults for every project, even when the
        // project did not use auction shares. Restore those defaults on rollback.
        DB::statement('UPDATE `najm_bahar_projects` SET `total_shares` = 100 WHERE `total_shares` IS NULL');
        DB::statement('UPDATE `najm_bahar_projects` SET `initial_auction_percent` = 10.00 WHERE `initial_auction_percent` IS NULL');
        DB::statement('ALTER TABLE `najm_bahar_projects` MODIFY `total_shares` INT UNSIGNED NOT NULL DEFAULT 100');
        DB::statement('ALTER TABLE `najm_bahar_projects` MODIFY `initial_auction_percent` DECIMAL(5,2) NOT NULL DEFAULT 10.00');
    }
};
