<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('location_proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposer_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('parent_location_id')->constrained('locations')->cascadeOnDelete();
            $table->foreignId('location_schema_id')->constrained('location_schemas')->cascadeOnDelete();
            $table->foreignId('location_type_id')->constrained('location_types')->cascadeOnDelete();
            $table->string('country_code', 8)->nullable();
            $table->string('canonical_name');
            $table->string('normalized_name');
            $table->json('localized_names')->nullable();
            $table->string('status', 32)->default('pending');
            $table->foreignId('resolved_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_reason')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->json('audit_log')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['parent_location_id', 'location_type_id', 'normalized_name'], 'location_proposals_candidate_lookup');
            $table->index(['status', 'created_at'], 'location_proposals_review_queue');
        });

        Schema::create('location_proposal_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_proposal_id')->constrained('location_proposals')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->json('evidence')->nullable();
            $table->timestamps();

            $table->unique(['location_proposal_id', 'user_id'], 'location_proposal_evidence_unique_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_proposal_evidence');
        Schema::dropIfExists('location_proposals');
    }
};
