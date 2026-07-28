<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('najm_bahar_projects', function (Blueprint $table) {
            $table->enum('project_visibility', ['public', 'private'])->default('public')->after('project_type');
            $table->enum('project_stage', ['idea', 'documented', 'prototype', 'active'])->default('idea')->after('project_visibility');
            $table->text('existing_assets')->nullable()->after('project_stage');

            $table->text('problem_statement')->nullable()->after('existing_assets');
            $table->text('solution_description')->nullable()->after('problem_statement');
            $table->text('value_proposition')->nullable()->after('solution_description');
            $table->enum('target_market', ['local', 'professional', 'general', 'external'])->nullable()->after('value_proposition');

            $table->bigInteger('base_value_min')->nullable()->after('target_market');
            $table->bigInteger('base_value_max')->nullable()->after('base_value_min');

            $table->unsignedInteger('total_shares')->default(100)->after('base_value_max');
            $table->decimal('initial_auction_percent', 5, 2)->default(10)->after('total_shares');
            $table->decimal('max_user_ownership_percent', 5, 2)->nullable()->after('initial_auction_percent');
            $table->enum('auction_period', ['monthly', 'quarterly'])->nullable()->after('max_user_ownership_percent');

            $table->enum('risk_level', ['low', 'medium', 'high'])->nullable()->after('auction_period');
            $table->json('main_risks')->nullable()->after('risk_level');
            $table->enum('oversight_type', ['guild', 'insurance', 'both', 'none'])->nullable()->after('main_risks');

            $table->enum('reporting_interval', ['monthly', 'quarterly'])->nullable()->after('oversight_type');
            $table->enum('fund_usage_scope', ['project_only'])->default('project_only')->after('reporting_interval');
            $table->boolean('accept_transparency')->default(false)->after('fund_usage_scope');

            $table->enum('failure_policy', ['refund', 'asset_conversion', 'vote'])->nullable()->after('accept_transparency');
            $table->enum('value_update_trigger', ['stage_progress', 'oversight_approval'])->nullable()->after('failure_policy');

            $table->boolean('accept_rules')->default(false)->after('value_update_trigger');

            $table->bigInteger('approved_value_min')->nullable()->after('accept_rules');
            $table->bigInteger('approved_value_max')->nullable()->after('approved_value_min');
            $table->bigInteger('current_base_value')->nullable()->after('approved_value_max');
            $table->bigInteger('current_market_price')->nullable()->after('current_base_value');
            $table->json('audit_log')->nullable()->after('current_market_price');
        });

        DB::statement(
            "ALTER TABLE `najm_bahar_projects` MODIFY `project_type` " .
            "ENUM('production','service','infrastructure','research','social') NOT NULL DEFAULT 'production'"
        );
    }

    public function down(): void
    {
        Schema::table('najm_bahar_projects', function (Blueprint $table) {
            $table->dropColumn([
                'project_visibility',
                'project_stage',
                'existing_assets',
                'problem_statement',
                'solution_description',
                'value_proposition',
                'target_market',
                'base_value_min',
                'base_value_max',
                'total_shares',
                'initial_auction_percent',
                'max_user_ownership_percent',
                'auction_period',
                'risk_level',
                'main_risks',
                'oversight_type',
                'reporting_interval',
                'fund_usage_scope',
                'accept_transparency',
                'failure_policy',
                'value_update_trigger',
                'accept_rules',
                'approved_value_min',
                'approved_value_max',
                'current_base_value',
                'current_market_price',
                'audit_log',
            ]);
        });

        DB::statement(
            "ALTER TABLE `najm_bahar_projects` MODIFY `project_type` " .
            "ENUM('public','private') NOT NULL DEFAULT 'public'"
        );
    }
};
