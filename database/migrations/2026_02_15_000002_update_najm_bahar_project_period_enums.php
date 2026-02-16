<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE `najm_bahar_projects` MODIFY `auction_period` " .
            "ENUM('monthly','quarterly','semi_annual','annual') NULL"
        );

        DB::statement(
            "ALTER TABLE `najm_bahar_projects` MODIFY `reporting_interval` " .
            "ENUM('monthly','quarterly','semi_annual','annual') NULL"
        );
    }

    public function down(): void
    {
        DB::statement(
            "ALTER TABLE `najm_bahar_projects` MODIFY `auction_period` " .
            "ENUM('monthly','quarterly') NULL"
        );

        DB::statement(
            "ALTER TABLE `najm_bahar_projects` MODIFY `reporting_interval` " .
            "ENUM('monthly','quarterly') NULL"
        );
    }
};
