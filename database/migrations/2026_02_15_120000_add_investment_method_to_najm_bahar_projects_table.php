<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('najm_bahar_projects')) {
            return;
        }

        Schema::table('najm_bahar_projects', function (Blueprint $table) {
            if (!Schema::hasColumn('najm_bahar_projects', 'investment_method')) {
                $column = $table->enum('investment_method', ['auction_shares', 'capital_participation'])
                    ->default('capital_participation');

                if (Schema::hasColumn('najm_bahar_projects', 'project_stage')) {
                    $column->after('project_stage');
                }
            }
        });

        // Allow auction-only projects to skip required_capital
        if (Schema::hasColumn('najm_bahar_projects', 'required_capital')) {
            DB::statement('ALTER TABLE `najm_bahar_projects` MODIFY `required_capital` BIGINT NULL');
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('najm_bahar_projects')) {
            return;
        }

        Schema::table('najm_bahar_projects', function (Blueprint $table) {
            if (Schema::hasColumn('najm_bahar_projects', 'investment_method')) {
                $table->dropColumn('investment_method');
            }
        });

        if (Schema::hasColumn('najm_bahar_projects', 'required_capital')) {
            DB::statement('ALTER TABLE `najm_bahar_projects` MODIFY `required_capital` BIGINT NOT NULL');
        }
    }
};
