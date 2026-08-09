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
            $table->foreignId('economic_action_id')->unique()->constrained('governance_economic_actions')->cascadeOnDelete();
            $table->foreignId('resolution_id')->constrained('governance_resolutions')->cascadeOnDelete();
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->foreignId('eligibility_snapshot_id')->constrained('governance_eligibility_snapshots')->restrictOnDelete();
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

            $table->index(['group_id', 'status']);
            $table->index(['status', 'due_at']);
        });

        Schema::create('governance_public_contribution_obligations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('governance_public_contribution_plans')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('amount_gol');
            $table->unsignedBigInteger('paid_gol')->default(0);
            $table->string('status', 30)->default('pending');
            $table->timestamp('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['plan_id', 'user_id'], 'gov_public_contribution_plan_user_unique');
            $table->index(['user_id', 'status']);
            $table->index(['plan_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('governance_public_contribution_obligations');
        Schema::dropIfExists('governance_public_contribution_plans');
    }
};
