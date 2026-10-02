<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('najm_bahar_projects', function (Blueprint $table) {
            $table->id();
            $table->morphs('owner');
            $table->unsignedBigInteger('category_level1_id')->nullable();
            $table->unsignedBigInteger('category_level2_id')->nullable();
            $table->unsignedBigInteger('category_level3_id')->nullable();
            $table->unsignedBigInteger('geographic_continent_id')->nullable();
            $table->unsignedBigInteger('geographic_country_id')->nullable();
            $table->unsignedBigInteger('geographic_province_id')->nullable();
            $table->unsignedBigInteger('geographic_county_id')->nullable();
            $table->unsignedBigInteger('geographic_section_id')->nullable();
            $table->unsignedBigInteger('geographic_city_id')->nullable();
            $table->unsignedBigInteger('geographic_rural_id')->nullable();
            $table->unsignedBigInteger('geographic_region_id')->nullable();
            $table->unsignedBigInteger('geographic_neighborhood_id')->nullable();
            $table->unsignedBigInteger('geographic_street_id')->nullable();
            $table->unsignedBigInteger('geographic_alley_id')->nullable();
            $table->string('title');
            $table->enum('project_type', ['production', 'service', 'infrastructure', 'research', 'social'])->default('production');
            $table->enum('project_visibility', ['public', 'private'])->default('public');
            $table->enum('project_stage', ['idea', 'documented', 'prototype', 'active'])->default('idea');
            $table->enum('investment_method', ['auction_shares', 'capital_participation'])->default('capital_participation');
            $table->text('existing_assets')->nullable();
            $table->text('problem_statement')->nullable();
            $table->text('solution_description')->nullable();
            $table->text('value_proposition')->nullable();
            $table->enum('target_market', ['local', 'professional', 'general', 'external'])->nullable();
            $table->bigInteger('base_value_min')->nullable();
            $table->bigInteger('base_value_max')->nullable();
            $table->unsignedInteger('total_shares')->default(100);
            $table->decimal('initial_auction_percent', 5, 2)->default(10.00);
            $table->decimal('max_user_ownership_percent', 5, 2)->nullable();
            $table->enum('auction_period', ['monthly', 'quarterly', 'semi_annual', 'annual'])->nullable();
            $table->enum('risk_level', ['low', 'medium', 'high'])->nullable();
            $table->longText('main_risks')->nullable();
            $table->enum('oversight_type', ['guild', 'insurance', 'both', 'none'])->nullable();
            $table->enum('reporting_interval', ['monthly', 'quarterly', 'semi_annual', 'annual'])->nullable();
            $table->enum('fund_usage_scope', ['project_only'])->default('project_only');
            $table->boolean('accept_transparency')->default(false);
            $table->enum('failure_policy', ['refund', 'asset_conversion', 'vote'])->nullable();
            $table->enum('value_update_trigger', ['stage_progress', 'oversight_approval'])->nullable();
            $table->boolean('accept_rules')->default(false);
            $table->bigInteger('approved_value_min')->nullable();
            $table->bigInteger('approved_value_max')->nullable();
            $table->bigInteger('current_base_value')->nullable();
            $table->bigInteger('current_market_price')->nullable();
            $table->longText('audit_log')->nullable();
            $table->text('summary');
            $table->longText('description')->nullable();
            $table->bigInteger('required_capital')->nullable();
            $table->decimal('profit_percentage', 5, 2)->nullable();
            $table->integer('investment_duration_months')->nullable();
            $table->longText('attachments')->nullable();
            $table->enum('status', ['draft', 'pending', 'under_review', 'approved', 'rejected', 'archived'])->default('draft');
            $table->text('admin_notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->string('assigned_to_type')->nullable();
            $table->unsignedBigInteger('assigned_to_id')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->text('assignment_note')->nullable();
            $table->enum('assignment_status', ['pending', 'under_review', 'completed', 'rejected'])->nullable();
            $table->text('assignment_review_note')->nullable();
            $table->timestamp('assignment_completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('category_level1_id')->references('id')->on('najm_bahar_project_categories')->onDelete('set null');
            $table->foreign('category_level2_id')->references('id')->on('najm_bahar_project_categories')->onDelete('set null');
            $table->foreign('category_level3_id')->references('id')->on('najm_bahar_project_categories')->onDelete('set null');

            $table->index('status');
            $table->index('project_type');
            $table->index(['category_level1_id', 'category_level2_id', 'category_level3_id'], 'category_index');
            $table->index('submitted_at');
            $table->index('approved_at');
            $table->index(['assigned_to_type', 'assigned_to_id']);
            $table->index('assignment_status');
            $table->index('assigned_at');
            $table->index(['geographic_continent_id', 'geographic_country_id', 'geographic_province_id'], 'najm_bahar_projects_geo_cont_country_prov_idx');
            $table->index('geographic_neighborhood_id', 'najm_bahar_projects_geo_neighborhood_idx');
            $table->index('geographic_region_id', 'najm_bahar_projects_geo_region_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('najm_bahar_projects');
    }
};