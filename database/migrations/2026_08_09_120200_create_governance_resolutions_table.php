<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('governance_resolutions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_id')->constrained('governance_proposals')->cascadeOnDelete();
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->foreignId('poll_id')->nullable()->constrained('polls')->nullOnDelete();
            $table->foreignId('adopted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 50)->default('general');
            $table->string('status', 30)->default('draft');
            $table->string('effect_status', 30)->default('none');
            $table->decimal('quorum_required_percent', 5, 2)->nullable();
            $table->decimal('approval_required_percent', 5, 2)->default(50.00);
            $table->unsignedBigInteger('eligible_voter_count')->nullable();
            $table->unsignedBigInteger('votes_cast')->default(0);
            $table->unsignedBigInteger('votes_for')->default(0);
            $table->unsignedBigInteger('votes_against')->default(0);
            $table->unsignedBigInteger('votes_abstain')->default(0);
            $table->json('financial_effect')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('effective_at')->nullable();
            $table->timestamp('adopted_at')->nullable();
            $table->timestamps();

            $table->index(['group_id', 'status']);
            $table->index(['proposal_id', 'status']);
            $table->index('effect_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('governance_resolutions');
    }
};
