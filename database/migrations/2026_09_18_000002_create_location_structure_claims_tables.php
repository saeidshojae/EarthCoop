<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('location_structure_claims')) {
            Schema::create('location_structure_claims', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('location_id')->constrained('locations')->cascadeOnDelete();
                $table->string('claim_type', 64);
                $table->string('status', 32)->default('pending');
                $table->foreignId('proposer_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('review_reason')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->json('metadata')->nullable();
                $table->json('audit_log')->nullable();
                $table->timestamps();
                $table->index(['location_id', 'claim_type', 'status'], 'location_structure_claim_open_lookup');
            });
        }

        if (! Schema::hasTable('location_structure_claim_evidence')) {
            Schema::create('location_structure_claim_evidence', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('location_structure_claim_id');
                $table->foreign('location_structure_claim_id', 'lsc_evidence_claim_fk')->references('id')->on('location_structure_claims')->cascadeOnDelete();
                $table->unsignedBigInteger('user_id');
                $table->foreign('user_id', 'lsc_evidence_user_fk')->references('id')->on('users')->cascadeOnDelete();
                $table->json('evidence')->nullable();
                $table->timestamps();
                $table->unique(['location_structure_claim_id', 'user_id'], 'location_structure_claim_user_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('location_structure_claim_evidence');
        Schema::dropIfExists('location_structure_claims');
    }
};
