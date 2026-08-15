<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('governance_public_contribution_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('economic_action_id');
            $table->foreignId('resolution_id');
            $table->foreignId('group_id');
            $table->foreignId('eligibility_snapshot_id');
            $table->string('status', 30)->default('open');
            $table->unsignedBigInteger('total_required_gol');
            $table->unsignedBigInteger('eligible_count');
            $table->unsignedBigInteger('base_amount_gol');
            $table->unsignedBigInteger('remainder_gol')->default(0);
            $table->timestamp('due_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique('economic_action_id', 'gov_pub_contrib_action_unique');
            $table->foreign('economic_action_id', 'gov_pub_contrib_action_fk')
                ->references('id')->on('governance_economic_actions')->cascadeOnDelete();
            $table->foreign('resolution_id', 'gov_pub_contrib_resolution_fk')
                ->references('id')->on('governance_resolutions')->cascadeOnDelete();
            $table->foreign('group_id', 'gov_pub_contrib_group_fk')
                ->references('id')->on('groups')->cascadeOnDelete();
            $table->foreign('eligibility_snapshot_id', 'gov_pub_contrib_snapshot_fk')
                ->references('id')->on('governance_eligibility_snapshots')->restrictOnDelete();

            $table->index(['group_id', 'status'], 'gov_pub_contrib_group_status_idx');
            $table->index(['status', 'due_at'], 'gov_pub_contrib_status_due_idx');
        });

        Schema::create('governance_public_contribution_obligations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id');
            $table->foreignId('user_id');
            $table->unsignedBigInteger('amount_gol');
            $table->unsignedBigInteger('paid_gol')->default(0);
            $table->string('status', 30)->default('pending');
            $table->timestamp('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('plan_id', 'gov_pub_obligation_plan_fk')
                ->references('id')->on('governance_public_contribution_plans')->cascadeOnDelete();
            $table->foreign('user_id', 'gov_pub_obligation_user_fk')
                ->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['plan_id', 'user_id'], 'gov_public_contribution_plan_user_unique');
            $table->index(['user_id', 'status'], 'gov_pub_obligation_user_status_idx');
            $table->index(['plan_id', 'status'], 'gov_pub_obligation_plan_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('governance_public_contribution_obligations');
        Schema::dropIfExists('governance_public_contribution_plans');
    }
};
