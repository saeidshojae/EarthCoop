<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('najm_bahar_projects', function (Blueprint $table) {
            $table->enum('investment_method', ['auction_shares', 'capital_participation'])
                ->default('capital_participation')
                ->after('project_stage');
        });

        // Allow auction-only projects to skip required_capital
        DB::statement('ALTER TABLE `najm_bahar_projects` MODIFY `required_capital` BIGINT NULL');
    }

    public function down(): void
    {
        Schema::table('najm_bahar_projects', function (Blueprint $table) {
            $table->dropColumn('investment_method');
        });

        DB::statement('ALTER TABLE `najm_bahar_projects` MODIFY `required_capital` BIGINT NOT NULL');
    }
};
